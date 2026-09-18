# Conventions du projet

## Langue

Le domaine métier est en **français** : filière, UE, EC, séance, créneau,
présence, établissement. Les noms de classes, de méthodes, de variables, de
tests et les commentaires suivent le domaine.

L'anglais reste là où il est imposé par le cadre technique : `store`, `update`,
`index`, `handle`, `boot`, les noms de colonnes déjà en place, les paramètres de
route existants.

Le dépôt porte l'héritage d'une migration linguistique inachevée — on trouve
`/admin/students` à côté de `/admin/filieres`, et `presence/pending` à côté de
`presence/manuelle` dans le même sous-groupe. **On ne renomme pas les routes
existantes** : deux clients en dépendent, dont une application mobile distribuée
en APK que l'on ne met pas à jour d'un claquement de doigts. Un renommage
n'aurait de sens qu'accompagné d'un préfixe de version (`/v1`).

La règle vaut donc pour le code **neuf**.

## Commentaires

Un commentaire dit **pourquoi**, pas quoi. La convention du projet — et c'est sa
meilleure habitude — est de citer le défaut que le code corrige, avec sa mesure
quand elle existe :

```php
// « exists:ecs,id » ne dit rien de l'établissement. La filière étant déduite de
// l'EC, l'EC d'une autre faculté créait la séance chez elle.
$this->authorizeEtablissement($ec->ue, $request, 'filiere');
```

Les accents sont écrits. Le code ancien en manque par endroits : on les ajoute
en passant, jamais dans un commit séparé.

## Tests

- Nom en français, `snake_case` : `test_un_etudiant_supprime_peut_etre_reinscrit`.
- Un test de régression **doit échouer sans le correctif**. On le vérifie avant
  de le considérer écrit.
- Un double de test ne doit jamais inventer une API. Une simulation d'appareil
  qui fournissait un `installationId` disparu d'expo-constants a masqué pendant
  des semaines une empreinte identique sur tous les téléphones d'un même modèle.
- Les vérifications de logique métier se testent unitairement, sans passer par
  HTTP : une base complète pour éprouver une règle de calcul coûte trop cher
  pour être refaite à chaque cas limite.

## Analyse statique

- `make phpstan` (backend) : niveau 5, avec un cliquet. Les constats antérieurs
  sont dans `phpstan-baseline.neon` ; tout **nouveau** constat échoue la CI. On
  ne l'ajoute pas à la baseline pour le faire taire : on corrige la cause.
- Tout fichier PHP **nouveau** commence par `declare(strict_types=1);`. Les
  fichiers existants l'adoptent au fil des modifications, un par un, tests à
  l'appui : le poser en bloc sur 150 fichiers changerait des conversions
  implicites (chaînes numériques des requêtes, colonnes Eloquent) sans qu'aucun
  test ne le signale.
- Frontend : `npm run ts:check` (tsc strict sur les `.ts`) et ESLint sur
  `.ts`/`.tsx`. Prettier n'est pas passé sur tout l'historique — il ne formaterait
  que du bruit — mais sur les fichiers touchés : `npx simple-git-hooks` (une fois,
  dans `frontend/`) active le hook de pré-commit `lint-staged`. Il n'est pas
  installé automatiquement.

## Sécurité

- Le cloisonnement par établissement est **fermé par défaut**. Un identifiant
  reçu dans une requête (`ec_id`, `filiere_id`, `salle_id`…) n'est jamais validé
  par un simple `exists:` : il faut vérifier l'appartenance à l'établissement.
- Un refus ne divulgue pas ce qu'il attendait : ni la distance à la salle, ni le
  rayon, ni le nom du réseau. Le détail va dans l'anomalie, côté administration.
- Aucun secret dans le dépôt, y compris dans un fichier de notes. Ce qui a été
  committé une fois est compromis : il faut le régénérer, pas seulement le
  retirer.

## Git

- L'auteur du projet est unique ; aucun commit ne porte d'attribution à un
  tiers, ni de `Co-authored-by`. Voir `.mailmap` pour l'unification des identités.
- Messages en Conventional Commits, en français :
  `fix(presences): le délégué n'affiche que le QR des séances qu'il suit`.
- On n'ajoute jamais `git add -A` : les fichiers se stagent un par un, le
  mémoire et les documents personnels n'ont rien à faire dans le dépôt.

## Dépendances

Une dépendance ajoutée doit être utilisée. Le dépôt a porté trois paquets
Composer morts (`turso-driver-laravel`, `lara-sms`, `laravel-google-calendar`),
dont l'un installait une extension native à la construction de l'image.
`composer.json` et `package.json` ne sont pas des listes de souhaits.
