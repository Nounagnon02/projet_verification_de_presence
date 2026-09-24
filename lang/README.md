# Traductions

Le **français est la langue source** : il n'a pas de fichier JSON, le texte
français est lui-même la clé. `__('Tableau de bord')` renvoie « Tableau de
bord » en français, et va chercher la traduction dans `lang/<code>.json` pour
les autres langues.

## Organisation

| Chemin | Contenu |
|---|---|
| `lang/<code>.json` | tout le texte de l'application, indexé sur le texte français |
| `lang/<code>/validation.php` | messages du validateur Laravel |
| `lang/<code>/auth.php`, `passwords.php`, `pagination.php` | messages du framework |
| `lang/<code>/date.php` | ordre des éléments de date (le nom des mois vient de Carbon) |
| `lang/<code>/badges.php` | badges, indexés sur la colonne stable `badges.condition` |
| `lang/_worksheet-<code>.csv` | fichier de travail pour un traducteur, **regénérable, jamais lu par l'application** |

`config/locales.php` est la source unique : code, nom natif, locale Carbon,
besoin de police étendue, et le drapeau `ready`.

## État des langues

| Langue | `ready` | État |
|---|---|---|
| `fr` Français | oui | langue source |
| `en` English | oui | traduit intégralement |
| `es` Español | oui | traduit intégralement |
| `yo` Yorùbá | **non** | **brouillon rédigé par Claude, jamais relu par un locuteur** |
| `fon` Fɔ̀ngbè | **non** | **non traduit** — voir `lang/_worksheet-fon.csv` |

Tant que `ready` vaut `false`, la langue n'est ni proposée dans le sélecteur,
ni sélectionnée par l'en-tête `Accept-Language`, ni acceptée par
`/language/<code>` : l'application reste en français. C'est volontaire.

## Faire relire ou traduire une langue

```bash
php artisan lang:worksheet fon      # écrit lang/_worksheet-fon.csv
```

Le CSV a quatre colonnes : le texte français, la traduction (à remplir ou à
corriger), un indicateur « pluriel », et le premier endroit du code où la
chaîne apparaît — utile pour savoir s'il s'agit d'un bouton, d'un titre ou
d'une phrase complète.

Points à respecter :

- **Les jetons `:count`, `:name`, `:date`, `:rate`, `:event`, `:done`,
  `:total`, `:user` doivent être recopiés tels quels**, ils sont remplacés à
  l'exécution.
- Les accolades `{name}`, `{date}`, `{event}` de la page Alertes aussi.
- Les lignes marquées « pluriel » contiennent deux formes séparées par `|`.
  Le yoruba et le fon n'ont qu'une forme de pluriel (catégorie CLDR `other`) :
  **écrire deux fois la même phrase**, Laravel en attend deux.
- Une chaîne contient du HTML (`<span class="text-accent">…</span>`) : garder
  la balise, traduire seulement le texte à l'intérieur.

Une fois le CSV rempli, reporter les valeurs dans `lang/<code>.json`, puis
passer `'ready' => true` dans `config/locales.php`, et lancer :

```bash
php artisan test --filter TranslationCoverageTest
```

Ce test échoue s'il manque une chaîne, si une chaîne n'est plus utilisée, ou
si le nombre de formes plurielles ne correspond pas.

## Ajouter une langue

1. Déclarer le code dans `config/locales.php` avec `'ready' => false`.
   - `carbon` : la locale à donner à Carbon pour les dates. Vérifier qu'elle
     existe dans `vendor/nesbot/carbon/src/Carbon/Lang/` ; sinon mettre `fr`.
   - `glyphs` : `extended` si la langue a besoin de caractères absents
     d'Atkinson Hyperlegible (ẹ U+1EB9, ọ U+1ECD, tons U+0300/U+0301). Le
     composant `<x-fonts />` bascule alors sur Noto Sans.
2. `php artisan lang:worksheet <code>`, faire traduire, reporter dans le JSON.
3. Ajouter le code à `TRANSLATED_LOCALES` dans
   `tests/Feature/TranslationCoverageTest.php`.
4. Passer `'ready' => true`.

## Pourquoi le fon n'est pas traduit

Le fon (`fon`, ISO 639-3) est absent de CLDR et de Carbon, et surtout : il
n'existe pas de traduction automatique fiable vers le fon pour du vocabulaire
d'interface. Google Translate grand public le gère depuis juillet 2024 mais
Cloud Translation ne l'expose pas ; NLLB-200 (`fon_Latn`) et
`masakhane/m2m100_418M_fr_fon_rel_news` sont entraînés sur des corpus
religieux (JW300) et de presse, sans vocabulaire logiciel. Une évaluation
publiée en 2026 sur le fongbé conclut que les métriques automatiques
corrèlent mal avec le jugement humain et qu'une relecture humaine est
indispensable.

Le fichier de travail est donc la bonne porte d'entrée : un locuteur remplit
la colonne `fon`, et rien ne s'affiche avant que `ready` passe à `true`.
