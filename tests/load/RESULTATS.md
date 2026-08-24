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
