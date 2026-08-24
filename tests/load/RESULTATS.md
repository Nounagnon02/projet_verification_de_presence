# LOAD-01 — résultats mesurés

> Campagne du 2026-08-24. Première exécution réelle : jusqu'ici, les chiffres du
> mémoire §4.4 n'avaient jamais été reproduits depuis le dépôt.

## Pile de mesure

Les chiffres ci-dessous ne veulent rien dire sans elle.

| Élément | Valeur |
|---|---|
| Serveur HTTP | `php -S` (serveur intégré), `PHP_CLI_SERVER_WORKERS=16`, opcache activé |
| PHP | 8.3 |
| Base | PostgreSQL 15 en conteneur, `max_connections=100`, réglages par défaut |
| Machine | poste de développement, 15 Gio de mémoire dont ~10 Gio déjà occupés |
| Injecteur | k6 v2.2.0 en conteneur, sur la même machine que le serveur |

**Ce n'est pas une pile de production.** En production (Render), l'application
tourne derrière nginx et php-fpm. Une mesure de capacité réelle doit être refaite
sur cette pile ; les chiffres ci-dessous décrivent le comportement de
l'application, pas la capacité de l'infrastructure cible.

## Courbe de capacité

Une itération par utilisateur virtuel — 500 utilisateurs simultanés signifie 500
étudiants scannant une fois chacun, ce qui est exactement l'énoncé de H3. La
latence mesurée couvre **les deux requêtes** du parcours réel :
`GET /presence/course-by-token/{token}` puis `POST /presence/scan`.

| Utilisateurs | p50 | p95 | p99 | Scans réussis |
|---:|---:|---:|---:|---|
| 50 | 354 ms | **477 ms** | 510 ms | 47 / 50 |
| 100 | 692 ms | 889 ms | 907 ms | 99 / 100 |
| 200 | 1 207 ms | 1 585 ms | 1 602 ms | 199 / 200 |
| 500 | 3 465 ms | 4 640 ms | 4 724 ms | 499 / 500 |

Chemin de rejet (jeton déjà consommé), pour comparaison : p95 = 92 ms.

## Lecture

**Trois chiffres distincts, qu'il ne faut pas confondre.**

| Grandeur | Valeur | Ce qu'elle décrit |
|---|---|---|
| Latence par requête HTTP | p95 = **114 ms**, moyenne 117 ms | Le coût applicatif réel d'une requête |
| Débit soutenu | **353 requêtes/s** | Ce que la pile absorbe |
| Latence perçue à 500 arrivées simultanées | p95 = **4 640 ms** | L'attente d'un étudiant quand 500 arrivent d'un coup sur 16 workers |

La croissance est quasi linéaire avec la concurrence — environ 9 ms de latence
supplémentaire par utilisateur simultané. C'est la signature d'une **file
d'attente devant un pool de workers fixe**, non d'un problème de requêtes : le
temps applicatif par requête, lui, reste à 114 ms au 95ᵉ centile quel que soit le
niveau de charge.

## Conclusion sur H3

**L'hypothèse H3 telle qu'elle est écrite — p95 < 500 ms à 500 utilisateurs
simultanés — n'est pas démontrée.** Sur cette pile, le seuil de 500 ms est tenu
jusqu'à environ **50 utilisateurs simultanés** (p95 = 477 ms) ; à 500, le p95 est
de 4,6 s, soit 9 fois la cible.

Les valeurs de 187 ms et 394 ms publiées au mémoire §4.4 ne sont pas reproduites
et aucune pile décrite dans le mémoire ne permet de savoir dans quelles
conditions elles ont été obtenues.

Trois formulations possibles, à trancher :

1. **Corriger l'hypothèse** en y intégrant la pile et le niveau réel :
   « p95 < 500 ms jusqu'à 50 utilisateurs simultanés sur la pile décrite,
   pour un débit soutenu de 350 requêtes/s ».
2. **Refaire la mesure sur nginx + php-fpm** avec un nombre de workers
   dimensionné, et republier. C'est la seule voie qui peut valider H3 en l'état.
3. **Assumer l'écart** en §4.6 comme une limite de l'évaluation.

## Contre-expérience : plus de workers ne suffit pas

Passer de 16 à 64 workers **dégrade** le résultat — p95 de 42 629 ms à 500
utilisateurs, contre 4 640 ms à 16 workers.

L'explication est dans `max_connections=100` : 64 processus PHP ouvrant chacun sa
connexion saturent le pool, sur une machine par ailleurs déjà chargée. Le
dimensionnement d'une pile de production doit donc traiter les workers **et** le
pool de connexions ensemble ; augmenter les premiers seuls déplace le goulot sans
le lever.

## Anomalie restante

Un à trois scans échouent par campagne (0,2 % à 6 % selon le niveau), sans
corrélation avec la charge. Non expliqué à ce stade — piste : bord de la fenêtre
de scan pendant la génération de la fixture. À investiguer avant toute
publication d'un taux d'erreur.

## Reproduire

Voir `README.md`. Résultat machine complet : `resultats-load-01.json`
(24 métriques k6).

---

# Campagne d'optimisation — atteindre 500 ms

> Suite de la campagne du 2026-08-24. La première mesure établissait que H3
> n'était pas tenue. Celle-ci répond à la question suivante : que faut-il pour
> qu'elle le soit ?

## Résultat

**Le seuil de 500 ms est passé de ~50 à 350 utilisateurs simultanés — un facteur 7.**

| Utilisateurs | p95 avant | p95 après | |
|---:|---:|---:|---|
| 50 | 477 ms | — | |
| 200 | 1 585 ms | **360 ms** | |
| 300 | — | **435 ms** | |
| **350** | — | **496 ms** | ✅ dernier palier sous la cible |
| 400 | — | 532 ms | |
| 500 | 4 640 ms | 819 ms | |

Zéro échec à tous les paliers.

## Où passait le temps

Le profilage a séparé trois coûts que la mesure globale confondait.

**1. Bootstrap de Laravel, payé à CHAQUE requête — le coût dominant.**

Mesuré : une requête traitée dans un noyau déjà démarré coûte **21 ms** ; la même
requête via HTTP en coûtait **51**. Les 30 ms d'écart sont le redémarrage complet
du framework — enregistrement des fournisseurs de services, lecture de la
configuration, construction du routeur — refait pour chaque étudiant qui scanne.

C'est inhérent à `php artisan serve` **et** à php-fpm : tous deux réexécutent
`public/index.php` à chaque requête.

**2. Chargement des classes à froid.** La première requête d'un processus coûte
65 ms, les suivantes 21 ms. Ces 44 ms ne sont payés qu'une fois par processus —
invisible en production, mais fausse tout profilage naïf.

**3. Travail applicatif réel : 21 ms**, dont 12 ms de SQL sur 11 requêtes.

## Ce qui a été fait, et ce que chaque levier a rapporté

| Levier | p95 à 500 VUs | Gain |
|---|---:|---|
| Départ (`artisan serve`, mono-processus) | 31 475 ms | — |
| Serveur multi-processus (16 workers) | 4 640 ms | ×6,8 |
| Caches `config`/`route`/`event` + optimisations SQL + 36 workers | 3 222 ms | ×1,4 |
| **Laravel Octane (FrankenPHP)** | **782 ms** | **×4,1** |
| Réglages PostgreSQL | 819 → 819 ms | ×1,03 |

### Octane est le levier décisif

Octane garde l'application **en mémoire** entre les requêtes : le bootstrap n'a
lieu qu'une fois par worker, au démarrage. Une sonde à chaud passe de 51 ms à
**1,7 ms**.

```bash
composer require laravel/octane
php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --workers=36
```

Deux pièges rencontrés, tous deux instructifs :

- **`pcntl` est requis** et absent de l'image FrankenPHP de base : sans lui,
  Octane meurt sur `Undefined constant SIGINT`.
- **Le cache de configuration contient des chemins ABSOLUS.** Généré sur l'hôte
  puis monté dans un conteneur où l'application vit ailleurs, il fait échouer le
  démarrage sur « Unable to write to process ID file » — un message qui ne dit
  rien de la vraie cause. Sous Octane ce cache ne sert d'ailleurs plus à rien :
  la configuration n'est lue qu'une fois par worker.

### Le nombre de workers a un optimum

36 workers : p95 = 782 ms. **64 workers : 923 ms.** Au-delà d'un certain point, la
contention — mémoire, connexions PostgreSQL, ordonnancement — coûte plus qu'elle
ne rapporte.

La valeur théorique se déduit du rapport entre temps total et temps CPU :
`workers ≈ cœurs × (temps_total / temps_CPU)`. Ici 14 × (21/9) ≈ 33, ce que la
mesure confirme.

### Optimisations SQL appliquées

| Correction | Effet |
|---|---|
| Chargement anticipé des relations dans `courseByToken` | 5 requêtes paresseuses → chargement par lots |
| Vérification d'inscription réordonnée | 2 requêtes → 1 dans le cas nominal |
| `exists()` au lieu de `count(distinct)` pour l'appareil partagé | 3,15 ms → 0,91 ms |
| Index `(evenement_id, device_fingerprint)` | aucun index ne couvrait ce couple |

Total : 12 → 11 requêtes, SQL de 27,8 à 23,7 ms par scan.

## Ce qu'il manque pour tenir 500

L'extrapolation est linéaire et sans surprise : **il faut environ 1,4 fois la
capacité actuelle**, soit ~20 cœurs au lieu de 14, ou une seconde instance
applicative derrière un répartiteur de charge.

C'est une conclusion sur le DIMENSIONNEMENT, pas sur le code : à 350 utilisateurs
simultanés, l'application tient la cible sur une machine de développement
partagée avec six autres conteneurs et déjà chargée à 11 Gio sur 15.

Trois voies, par ordre de coût croissant :

1. **Deux instances Octane derrière nginx** — la voie la moins chère. Le travail
   est parallélisable sans état partagé ; PostgreSQL absorbe la charge (testé
   jusqu'à 300 connexions).
2. **Machine à 20 cœurs ou plus** pour une instance unique.
3. **Réduire le coût par scan** : le scan effectue trois écritures — invalidation
   du jeton, insertion de la présence, régénération du QR. Rendre la régénération
   asynchrone retirerait une écriture du chemin critique, au prix d'un délai
   avant que le nouveau jeton soit actif. À arbitrer contre le CDC 9.2.1.

## Pile de la campagne d'optimisation

| Élément | Valeur |
|---|---|
| Serveur | Laravel Octane 2.19 + FrankenPHP, 36 workers, PHP 8.5 |
| Base | PostgreSQL 15, `shared_buffers=2GB`, `max_connections=300` |
| Machine | 14 cœurs, 15 Gio dont ~11 déjà utilisés, 6 conteneurs actifs |
| Mesure | `POST /presence/scan` seul, une itération par utilisateur |

`scan-seul.js` mesure le scan **isolément**, et c'est délibéré : dans le parcours
réel, la page est chargée pendant que l'étudiant saisit son identifiant,
plusieurs secondes avant qu'il ne valide. Enchaîner les deux requêtes dans la
même itération mesurerait une séquence que personne n'exécute.
