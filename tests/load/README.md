# Test de charge — LOAD-01

Rend reproductible l'hypothese H3 du memoire : p95 < 500 ms a 500 utilisateurs
simultanes sur `POST /presence/scan`.

## Le piege a connaitre avant de lire un chiffre

Chaque scan reussi **invalide son jeton** de QR Code (anti-rejeu, CDC 9.2.1), et
la contrainte d'unicite `(etudiant_id, evenement_id)` interdit a un etudiant de
scanner deux fois le meme cours.

Consequence : une campagne naive — 500 utilisateurs sur un evenement et un jeton
— mesure **un** scan reussi et **499 refus 410**. Le chiffre obtenu decrit le
chemin de rejet, qui ne touche ni l'ecriture de presence, ni le georeperage, ni
la detection d'appareil partage. Il est plusieurs fois meilleur que le parcours
reel.

D'ou deux elements indissociables :

1. `preparer-charge.php` genere un couple **(etudiant, evenement, jeton) distinct
   par utilisateur virtuel** ;
2. `scan.k6.js` mesure separement le parcours nominal et le chemin de rejet, et
   n'applique le seuil H3 qu'au premier.

## Base de donnees : jamais celle de la suite PHPUnit

Ce generateur ecrit des lignes **reelles et persistantes**, hors de toute
transaction. La suite PHPUnit, elle, compte des lignes et cree ses propres
fixtures — et `annees_academiques.libelle` est unique **globalement**, pas par
etablissement.

Consequence constatee : une seule campagne lancee sur `presence_uac_test` a fait
echouer **149 tests d'un coup**, avec des messages qui ne renvoyaient jamais vers
un test de charge. Le script refuse desormais toute base dont le nom contient
`_test` (contournable par `--forcer`, en connaissance de cause).

Utiliser une base dediee :

```bash
docker exec uac-test-pg createdb -U postgres presence_uac_charge
```

## Execution

```bash
cd backend

# 1. Base dediee, migree
DB_DATABASE=presence_uac_charge php artisan migrate --force

# 2. Backend sur cette base
DB_DATABASE=presence_uac_charge php artisan serve --port=8000 &

# 3. Jeu de donnees — A REGENERER AVANT CHAQUE EXECUTION
DB_DATABASE=presence_uac_charge php ../tests/load/preparer-charge.php \
  --vus=500 > /tmp/charge.json

# 4. Campagne
k6 run -e JEU=/tmp/charge.json -e VUS=500 -e BASE_URL=http://localhost:8000 \
  ../tests/load/scan.k6.js
```

Le generateur ecrit son journal sur STDERR et le JSON sur STDOUT ; il annonce la
fenetre de scan ouverte, qui dure environ 25 minutes. La campagne doit s'y
inscrire — au-dela, tous les scans repartent en 403 « prise de presence
terminee ».

Un jeu rejoue ne mesure que des 410. Le script le signale par le controle
« jeton non deja consomme ».

## Ce qui est mesure

Le parcours nominal compte **les deux requetes** du parcours reel :

1. `GET /presence/course-by-token/{token}` — le client y recupere le defi
   anti-fraude, que le serveur seul peut emettre ;
2. `POST /presence/scan`.

Mesurer le seul POST sous-estimerait la latence percue par l'etudiant.

## Seuils

| Metrique | Cible | Origine |
|---|---|---|
| `latence_nominale` p50 | < 500 ms | memoire H3 / §4.4 |
| `latence_nominale` p95 | < 500 ms | memoire H3 / §4.4 |
| `latence_nominale` p99 | < 1000 ms | plan de tests LOAD-01 |
| `taux_de_succes_nominal` | > 99 % | plan de tests LOAD-01 |

## Nettoyage

Le generateur supprime son propre jeu au demarrage : etablissements dont le code
commence par `CHARGE-K6`, et tout ce qui en depend — **y compris l'annee
academique**, dont l'oubli etait precisement la cause des 149 echecs.

Les annees de charge utilisent la plage reservee `2090-2091`, pour ne jamais
entrer en collision avec une annee realiste. Le format `AAAA-AAAA` est conserve :
l'identifiant unique des etudiants le reprend tel quel (CDC 7.1.3).

`--garder` conserve le jeu precedent, mais ses jetons sont consommes : il n'est
plus utilisable pour une mesure nominale.

Le script refuse de tourner si `APP_ENV` vaut `production`, ou si la base cible
porte `_test` dans son nom.

## Etat

Script et generateur versionnes et valides de bout en bout : le jeu produit
donne bien un `201` sur le parcours complet defi + scan. **La campagne a 500
utilisateurs n'a pas encore ete executee** — k6 n'est pas installe dans cet
environnement. Les chiffres du memoire §4.4 (187 ms / 394 ms) restent donc non
reproduits a ce jour.
