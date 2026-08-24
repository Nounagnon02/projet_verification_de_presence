# Passe de securite — OWASP ZAP

Rend reproductible le tableau du memoire §4.3 (OWASP Top 10 2021 : 8 PASSE,
2 PARTIEL). Sans cette configuration versionnee, ce tableau n'est qu'une
affirmation.

## Prerequis

Un backend joignable et une base de test peuplee :

```bash
cd backend
make db-up
php artisan migrate --force
php artisan serve --port=8000
```

## Execution

```bash
docker run --rm --network host \
  -v "$(pwd)/tests/security:/zap/wrk/:rw" \
  ghcr.io/zaproxy/zaproxy:stable zap-api-scan.py \
  -t http://localhost:8000/api/docs/json -f openapi \
  -c zap-baseline.conf \
  -r rapport-zap.html -J rapport-zap.json
```

Code de sortie non nul = au moins une regle marquee `FAIL` a declenche.

## Pourquoi la cible est /api/docs/json

C'est une API sans interface serveur : un parcours de spider sur la racine ne
trouverait rien a explorer. La description OpenAPI donne a ZAP la liste des
routes, leurs methodes et leurs parametres — c'est la seule facon d'obtenir une
couverture reelle.

Consequence a verifier avant toute campagne : `/api/docs/json` doit refleter
`routes/api.php`. Une route absente de la description n'est pas testee, et le
rapport sera vert pour une mauvaise raison. C'est l'objet du cas BE-DOC-01 du
plan de tests.

## Ce que cette passe ne couvre pas

`zap-api-scan` en mode baseline est **passif** sur la majeure partie de la
surface. Il ne remplace pas :

- **A01 Broken Access Control** — cloisonnement par etablissement, capacites de
  jeton, IDOR. ZAP ne connait pas les regles metier. Couvert par `SEC-A01-01/02`
  cote PHPUnit, ou l'assertion est exacte.
- **A07 Auth failures** — limitation de debit (5/min sur la connexion, 3/min sur
  le scan). Une passe ZAP la declenche et se fait bloquer, ce qui masque le
  reste. A tester separement.
- **A02 Crypto** — `BCRYPT_ROUNDS=12` en production. Verification de
  configuration, hors de portee d'un scan.

Autrement dit : ZAP couvre l'injection, le XSS et les fuites d'information. Les
trois categories ci-dessus restent du ressort de la suite PHPUnit, et le memoire
doit le dire.

## Suppressions

Chaque `IGNORE` de `zap-baseline.conf` porte son motif. Ajouter une suppression
sans justification vide la passe de son sens : la regle a ete ecartee, pas
traitee.
