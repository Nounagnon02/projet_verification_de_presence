# Évaluation du module IA — résultats mesurés

> Campagne du 2026-08-24, provider Gemini. Première mesure réelle du module
> d'extraction depuis le dépôt.

## Ce qui ne peut pas être reproduit, et pourquoi

Les **45 documents** sur lesquels le mémoire §4.5 annonce 89,1 % d'extraction
correcte **ne sont pas dans le dépôt**. Ce chiffre n'est donc pas re-mesurable, et
aucun outillage n'y changera rien : on ne peut pas réévaluer un corpus qu'on n'a
plus. C'est une limite définitive, à assumer en §4.6.

Ce qui est fait à la place : un corpus de 9 documents dont la vérité terrain est
connue **par construction** (générée en même temps que le PDF), et donc des
chiffres réels et reproductibles. Le mémoire peut publier ceux-là, en disant
combien de documents ils couvrent.

## Pile de mesure

| Élément | Valeur |
|---|---|
| Provider | Gemini, modèle `gemini-3.6-flash` |
| Corpus | 9 documents générés par `generer-corpus.php` |
| Métriques | TEC (extraction correcte), TFP (faux positifs), SCM (confiance moyenne) |
| Mode | `--reel` (appels API réels) |

## Résultats

| Document | Type | TEC | TFP | Conf. | Détail |
|---|---|---:|---:|---:|---|
| `edt-01-simple` | EDT hebdo | **0 %** | 0 % | 0,00 | 0/20 champs, extraction vide |
| `edt-02-abreviations` | EDT hebdo | **0 %** | 0 % | 0,00 | 0/15 champs, extraction vide |
| `edt-03-seances-longues` | EDT hebdo | **0 %** | 0 % | 0,00 | 0/10 champs, extraction vide |
| `edt-04-creneau-unique` | EDT hebdo | **0 %** | 0 % | 0,00 | 0/5 champs, extraction vide |
| `edt-05-date` | EDT **daté** | 80 % | 0 % | 1,00 | 16/20 champs |
| `maq-01-simple` | Maquette | **100 %** | 0 % | 0,95 | 12/12 champs |
| `maq-02-volumes-incoherents` | Maquette | **100 %** | 0 % | 0,95 | 6/6 champs |
| `maq-03-nombreux-ec` | Maquette | **100 %** | 0 % | 0,95 | 18/18 champs |
| `hors-01-note-de-service` | Hors sujet | **100 %** | 0 % | 0,00 | refus correct |
| **MOYENNE** | | **53,3 %** | **0 %** | 0,43 | 0 analyse en échec sur 9 |

## Lecture

**Le résultat global de 53,3 % cache deux comportements opposés.**

Sur les types de documents que le pipeline sait traiter — maquettes pédagogiques
et plannings datés — l'extraction est **excellente** : 100 % sur les trois
maquettes, dont celle aux volumes horaires volontairement incohérents, où le
modèle a rapporté ce qu'il lisait sans chercher à corriger. C'est le comportement
souhaitable.

Sur les emplois du temps **hebdomadaires** — jour de la semaine et horaire, sans
date calendaire — l'extraction rend **systématiquement vide**. Quatre documents
sur quatre.

### TFP = 0 % sur l'ensemble du corpus

C'est le résultat le plus important, et il tient : **le modèle n'invente jamais**.
Aucun créneau, aucun EC produit ne correspond à rien dans le document. La note de
service administrative a été correctement refusée plutôt que transformée en
emploi du temps imaginaire.

L'exigence du mémoire — TFP ≤ 2 % — est donc largement tenue : 0 %. Et c'est
l'exigence qui compte le plus, car un créneau inventé crée un cours fantôme, donc
des absences pour des étudiants réels.

## Défaut trouvé : le pipeline n'accepte que les emplois du temps datés

Deux causes, dans le code, et la seconde est la plus grave :

1. `GeminiProvider::getSchedulePrompt()` exige `date` au format `YYYY-MM-DD`. Un
   emploi du temps hebdomadaire n'en contient pas : le modèle suit correctement
   la consigne et rend un tableau vide.

2. `GeminiProvider::processScheduleResult()`, ligne 167 :
   `array_filter(fn($e) => !empty($e['ec']) && !empty($e['date']))`.
   **Tout événement sans date est silencieusement écarté.** Même si le prompt
   était corrigé pour extraire des créneaux hebdomadaires, ils seraient jetés ici.

C'est incohérent avec le reste du système, qui modélise précisément des créneaux
hebdomadaires : table `emplois_du_temps`, colonne `jour_semaine`, service
`ScheduleSlotResolver`, endpoint `/admin/evenements/creneaux-emploi-du-temps`, et
commande `events:generate-from-schedule` qui transforme des créneaux récurrents en
événements datés. L'import IA est la seule pièce à ignorer ce modèle.

Conséquence pour l'utilisateur : un administrateur qui importe un emploi du temps
universitaire au format habituel obtient un écran de validation vide, avec un
score de confiance de 0 et le message générique « Vérification manuelle requise ».
Rien ne lui dit que le document a été lu mais que ses créneaux ont été écartés
faute de dates.

**Non corrigé ici** : rendre le pipeline capable de traiter les créneaux
hebdomadaires est un changement fonctionnel (nouveau prompt, prise en charge de
`jour_semaine` dans `processScheduleResult`, résolution en dates via
`ScheduleSlotResolver`), qui dépasse la mise en place de la mesure. Le défaut est
documenté pour arbitrage.

## Défaut corrigé en cours de campagne : tout l'import IA était mort

La première exécution a échoué sur **toutes** les analyses :

```
Erreur API Gemini (404): This model models/gemini-2.0-flash is no longer
available. Please update your code to use models/gemini-3.6-flash
```

Le nom du modèle était **codé en dur** dans chaque provider. Google ayant retiré
`gemini-2.0-flash`, toute analyse de document échouait en production — l'import
IA, c'est-à-dire l'hypothèse H2 du mémoire, était entièrement hors service.

Aucun test ne pouvait le voir : le seul test d'import simule le provider. C'est
exactement la classe de panne qu'un test simulé ne détecte jamais.

Corrigé : le modèle est désormais lu dans `config/ai.php`, surchargeable par
variable d'environnement (`GEMINI_MODEL`, `GROQ_MODEL`, `OPENROUTER_MODEL`). Les
deux autres providers portaient des identifiants tout aussi périmés —
`mixtral-8x7b-32768`, retiré du catalogue Groq, et
`google/gemini-2.5-flash-preview-04-17`, un identifiant de préversion éphémère
par construction.

Un modèle a une durée de vie de quelques mois : son nom appartient à la
configuration, pas au code.

## Non-déterminisme, et pourquoi le mode rejeu existe

Le même document `edt-05-date` a donné 100 % lors d'un premier appel et 80 % au
suivant, sans qu'aucun paramètre ne change. C'est la nature d'un modèle
génératif.

D'où les deux modes du harnais :

- `--reel` — appelle le provider. Consomme du quota, non déterministe. C'est le
  mode de la campagne de mesure.
- défaut — rejoue les réponses enregistrées dans `enregistrements/`. Déterministe
  et gratuit, donc utilisable en intégration continue.

Sans cette séparation, ou bien la CI brûle du quota à chaque exécution, ou bien la
mesure n'est jamais refaite. Les deux arrivent en pratique.

## Piège rencontré en écrivant le harnais

La première version comparait les extractions à un schéma que j'avais supposé
(`jour`, `ec_code`) alors que les providers produisent `{ec, date, heure_debut,
heure_fin, salle}`. Résultat : une extraction **parfaite** — 4 événements sur 4,
tous les champs corrects — était notée **0 %**.

Un harnais d'évaluation qui mesure contre un schéma inventé mesure la conformité
du nommage, pas la qualité de l'extraction. La comparaison porte désormais sur le
schéma réellement produit, et le champ `ec` est vérifié par inclusion puisqu'il
concatène volontiers le code et l'intitulé.

## Ce qui reste à faire

| Cas du plan | État |
|---|---|
| IA-01 corpus versionné | ✅ 9 documents, générateur et vérités terrain versionnés |
| IA-02 TEC par type | ✅ mesuré, voir tableau |
| IA-03 TFP ≤ 2 % | ✅ **0 %** |
| IA-04 SCM ≥ 0,84 | ⚠️ 0,43 en moyenne — mais 0,95 sur les documents traitables ; la moyenne est écrasée par les quatre extractions vides |
| IA-05 bascule de provider | ❌ Groq et OpenRouter non mesurés (modèles à valider) |
| IA-06 indisponibilité provider | ❌ non testé (429/500) |
| IA-07 réponse non-JSON | ❌ non testé |

Le corpus reste petit : 9 documents contre 45. Les chiffres sont réels et
reproductibles, mais leur intervalle de confiance est large. Les publier exige de
dire combien de documents ils couvrent.

## Reproduire

```bash
cd backend
php ../tests/ia/generer-corpus.php                    # regénère les PDF
php ../tests/ia/evaluer.php --reel --provider=gemini  # campagne
php ../tests/ia/evaluer.php --provider=gemini         # rejeu, gratuit
```

Les PDF ne sont pas versionnés : ils se régénèrent en quelques secondes depuis
`generer-corpus.php`, qui est la source de vérité. Sont versionnés le générateur,
les vérités terrain, les enregistrements de réponses et les résultats.

---

# Reprise du 2026-09-18 — le défaut des emplois du temps hebdomadaires est corrigé

> Mesure en mode **rejeu** (déterministe, sans appel au fournisseur), sur les
> réponses réenregistrées au commit `5d73fce`. Elle ne remplace pas une campagne
> `--reel` : elle mesure le pipeline d'extraction, pas la variabilité du modèle.

## Résultats

| Indicateur | 2026-08-24 (campagne réelle) | 2026-09-18 (rejeu) |
|---|---:|---:|
| Documents | 9 | 8 |
| TEC moyen | 53,3 % | **100 %** |
| TFP moyen | 0 % | **0 %** |
| SCM | 0,43 | **0,856** |
| Analyses en échec | 0 / 9 | 0 / 8 |

Les quatre emplois du temps **hebdomadaires** qui rendaient une extraction vide
— `edt-01-simple`, `edt-02-abreviations`, `edt-03-seances-longues`,
`edt-04-creneau-unique` — sont désormais extraits à 100 %. Le défaut décrit plus
haut (« le pipeline n'accepte que les emplois du temps datés ») a été corrigé
entre-temps par la reconstruction de l'import hebdomadaire.

`edt-05-date` ne fait plus partie du corpus : le générateur en produit huit.

## Ce que cela change pour le mémoire

| Cas du plan | État au 2026-08-24 | État au 2026-09-18 |
|---|---|---|
| IA-02 TEC par type | 53,3 % en moyenne, 0 % sur les EDT hebdomadaires | 100 % sur les huit documents |
| IA-03 TFP ≤ 2 % | ✅ 0 % | ✅ 0 % |
| IA-04 SCM ≥ 0,84 | ⚠️ 0,43 | ✅ 0,856 |
| IA-05 bascule de fournisseur | ❌ Groq et OpenRouter non mesurés | ❌ inchangé |
| IA-06 indisponibilité du fournisseur | ❌ | ❌ |
| IA-07 réponse non-JSON | ❌ | ❌ |

Le corpus reste petit : **huit documents**, contre les 45 annoncés au §4.5 et
définitivement perdus. Publier ces chiffres exige de dire sur combien de
documents ils portent, et qu'ils proviennent d'un rejeu.

## Reproduire

Le rejeu est désormais exécuté à chaque intégration continue
(`.github/workflows/ci.yml`, tâche « ia ») : il est déterministe et ne consomme
aucun quota, ce pour quoi le mode avait été construit.

```bash
cd backend
php ../tests/ia/generer-corpus.php
php ../tests/ia/evaluer.php --provider=gemini
```
