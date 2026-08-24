# Passe OWASP ZAP — résultats mesurés

> Campagne du 2026-08-24. Première exécution réelle : jusqu'ici, le tableau du
> mémoire §4.3 n'était appuyé par aucune passe reproductible.

## Ce qui a été exécuté

| Élément | Valeur |
|---|---|
| Outil | `zap-api-scan.py`, image `ghcr.io/zaproxy/zaproxy:stable` |
| Cible | `http://127.0.0.1:8100/api/docs/json`, format OpenAPI |
| Application | `php -S`, 8–16 workers, opcache activé, base PostgreSQL 15 dédiée |
| Configuration | `tests/security/zap-baseline.conf` |

## Résultat

| Indicateur | Valeur |
|---|---:|
| Règles passées | **119** |
| Échecs sur règles bloquantes | **0** |
| Avertissements | 1 (bruit, voir plus bas) |

Aucune des catégories bloquantes ne déclenche : injection SQL, XSS réfléchi et
persistant, injection de code côté serveur, injection de commande système,
injection d'en-tête HTTP, parcours de répertoires, inclusion de fichier distant,
Log4Shell, Spring4Shell, XXE, SSTI, XPath, padding oracle.

## Deux défauts trouvés, tous deux corrigés

### 1. Divulgation de la version de PHP

`X-Powered-By: PHP/8.3.x` était renvoyé sur toutes les réponses, livrant la
version exacte de l'interpréteur — de quoi cibler une CVE connue sans travail de
découverte. Règle ZAP 10037.

Corrigé dans `SecurityHeaders` (`headers->remove('X-Powered-By')` et
`header_remove()`). Le réglage `expose_php = Off` reste la solution de fond mais
dépend de l'hébergeur ; le retrait applicatif, lui, ne dépend de personne.
Vérifié : l'avertissement a disparu à la passe suivante.

### 2. Erreur 500 sur un endpoint public

`GET /presence/course-by-token/{token}` — route **publique, non authentifiée** —
répondait **500** pour tout jeton mal formé. La colonne `token` est de type
`uuid` : Postgres refusait la valeur, l'exception remontait, et la réponse
divulguait le type d'erreur applicative. Règles ZAP 100000 et 90022.

Corrigé par un contrôle `Str::isUuid()` en amont de la requête : un jeton mal
formé n'existe pas, la réponse est donc 404, identique à celle d'un jeton
inconnu. Distinguer les deux cas apprendrait à un attaquant si le format est bon.

Sept tests de régression ajoutés dans `PresenceScanTest`, dont une charge
d'injection SQL et une de XSS, avec vérification qu'aucun `SQLSTATE`,
`PDOException` ni chemin `vendor/laravel` ne fuit dans le corps.

**Vérification** : les deux URL exactes signalées par ZAP renvoient désormais
404, et le journal du serveur ne contient plus aucun 500. La passe complète de
confirmation n'a pas pu être menée au bout (mémoire de la machine) : c'est une
vérification ciblée, pas un rapport ZAP vierge.

## Défaut trouvé en préparant la passe : la documentation d'API était cassée

`GET /api/docs/json` répondait **500**. `docs/openapi.yaml` contenait trois
erreurs de syntaxe : deux clés de chemin dupliquées (`/admin/students/{id}/ecs`
et `/admin/tickets/{id}`, où `get` et `post` étaient déclarés sous deux clés
séparées au lieu d'être fusionnés) et une valeur non quotée contenant un
deux-points. Le parseur YAML s'arrête à la première erreur : chaque correction
révélait la suivante.

Ce n'est pas un problème de documentation seulement. **La passe ZAP est pilotée
par cette spécification** : sans elle, il n'y a pas de cible du tout.

En la réparant, la confrontation aux routes réelles a révélé :

- **7 chemins documentés qui n'existent pas**, dont quatre décrivant la
  messagerie instantanée supprimée du produit. Retirés.
- **31 routes réelles non documentées**, dont toute l'authentification étudiante
  et le QR du délégué — c'est-à-dire des points d'entrée que la passe ne testait
  pas. Les cinq surfaces non authentifiées ont été ajoutées ; il en reste 27,
  plafonnées par un test.

`tests/Feature/ApiDocumentationTest.php` verrouille tout cela : spécification
analysable, endpoint qui répond, zéro chemin fantôme, plafond sur les routes non
documentées, et présence obligatoire des points d'entrée non authentifiés.

## ⚠ Danger de configuration à connaître

La spécification déclare trois serveurs, dont **`https://api.presence.uac.bj/api`
(production)**. `zap-api-scan` envoie ses charges d'attaque à **tous** les
serveurs déclarés.

Lors de la première passe, ZAP a effectivement scanné deux cibles : la mienne, et
`http://localhost:8000` — un serveur de développement qui tournait par ailleurs.
La production n'a pas été touchée **uniquement parce que son nom de domaine ne
résout pas depuis cette machine**. Sur un poste où il résoudrait, la passe
l'aurait attaquée.

**`-O <cible>` est donc obligatoire, pas optionnel.** Il force toutes les
requêtes vers l'hôte indiqué et ignore les serveurs de la spécification.

## Ce que cette passe ne couvre pas

Inchangé depuis le README, et à écrire dans le mémoire :

- **A01 Broken Access Control** — cloisonnement par établissement, capacités de
  jeton, IDOR. ZAP ignore les règles métier.
- **A07 Auth failures** — limitation de débit. Une passe ZAP la déclenche et se
  fait bloquer, ce qui masque le reste.
- **A02 Crypto** — `BCRYPT_ROUNDS=12` en production, vérification de
  configuration.

Ces trois catégories relèvent de la suite PHPUnit. Le tableau §4.3 doit dire
lesquelles des dix catégories sont couvertes par ZAP et lesquelles par les tests.

## Reproduire

Voir `README.md`. Rapport machine : `rapport-zap.json`.
