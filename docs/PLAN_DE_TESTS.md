# Plan de tests — SGP-UAC

> Établi le 2026-08-21 après analyse du code (`backend/`, `frontend/`, `mobile-app/`, `tests/`),
> du cahier des charges (`CDC_UAC_Presence_v2.docx`) et du mémoire (`Memoire_de_Prince.pdf`).
> Toutes les mesures de la section 1 ont été relevées en exécutant les suites, pas déduites.

---

## 1. État des lieux mesuré

| Couche | Outil | Fichiers | Tests | Statut constaté |
|---|---|---:|---:|---|
| Frontend | Vitest 4.1 + React Testing Library | 42 | 211 | ✅ 42/42 verts (7,2 s) |
| Backend | PHPUnit (`php artisan test`) | 39 | 265 | ✅ 265/265 verts (1 388 assertions) au 2026-08-22 ; exige un Postgres jetable sur `127.0.0.1:55433` (`docker start uac-test-pg`) |
| E2E / API | Playwright 1.61 (`tests/`) | 7 | 39 | 6 fichiers API + 1 fichier navigateur, cibles `prod` (Render/Vercel) et `local` |
| Mobile | — | 0 | 0 | aucun test ; seul `npm run ts:check` (tsc --noEmit) |
| Couverture | — | — | — | **aucune mesure possible** : `@vitest/coverage-v8` non installé, pas d'appel `--coverage` côté PHPUnit |
| Intégration continue | — | — | — | **absente** (`.github/` inexistant) |
| Charge (k6) | — | — | — | **aucun script dans le dépôt** |
| Sécurité (OWASP ZAP) | — | — | — | **aucune configuration dans le dépôt** |

### Objectifs à atteindre, tels qu'écrits dans le mémoire

| Réf. | Exigence | Vérifiable aujourd'hui ? |
|---|---|---|
| §2.1.3 | Couverture de tests ≥ 70 % | ❌ aucun outil de couverture installé |
| H3 / §4.4 | p95 < 500 ms à 500 utilisateurs simultanés | ❌ script k6 absent du dépôt |
| §4.3 | OWASP Top 10 2021 — 8 PASSÉ / 2 PARTIEL | ❌ configuration ZAP absente du dépôt |
| H1 / §4.2 | 7 scénarios fonctionnels de scan | ⚠️ couverts côté backend, jamais depuis un client réel |
| H2 / §4.5 | Extraction IA sur 45 documents (89,1 %) | ❌ corpus et harnais d'évaluation absents du dépôt |

**Conclusion de l'état des lieux :** 409 tests existent et passent, mais aucun des cinq chiffres
publiés au chapitre 4 du mémoire n'est reproductible depuis le dépôt. Le plan ci-dessous a deux
objectifs distincts : (a) couvrir le produit, (b) rendre le chapitre 4 reproductible.

---

## 1 bis. Avancement au 2026-08-22

Branche `fix/plan-de-tests-corrections`, aucun push distant.

### Bloqueurs de la section 2

| Constat | État | Nature |
|---|---|---|
| B1 — `scan_challenge` incompatible | ✅ corrigé | défi désormais émis par le serveur |
| B2 — page du QR sans `scan_challenge` | ✅ corrigé | les trois clients envoient le défi du serveur |
| B3 — validation manuelle sans écran | ✅ corrigé | `PresenceQueuePage` construite et routée |
| B4 — fenêtre de scan divergente | ✅ tranché | le code fait foi, texte de remplacement fourni pour le mémoire |
| B5 — endpoints sans interface | 🔶 partiel | inscriptions, sessions actives, modèles CSV construits ; 2FA restant |
| B6 — endpoint fantôme | ✅ corrigé | `/user` porte la relation `etablissement` |
| B7 — code mort | ✅ tranché | `ImportPage` routée, 3 pages supprimées, `Drawer` conservé |
| B8 — exports de rapports | ✅ corrigé | cible réelle, vrai PDF, filtres transmis |

### Défauts produit découverts en cours de route

Aucun n'était documenté dans l'état des lieux initial : tous ont été mis au jour par les tests
écrits pour couvrir des contrôleurs qui n'en avaient aucun.

| Défaut | Impact utilisateur | Correctif |
|---|---|---|
| `users.group` `NOT NULL` omis à la création de faculté | 500 systématique, multi-établissements inutilisable | valeur renseignée + transaction |
| Renvoi d'identifiants sans révocation de jeton | l'ancien mot de passe restait exploitable | `tokens()->delete()` |
| `Anomaly::member()` vers une classe inexistante | **`GET /admin/dashboard` en 500 dès la première anomalie** | eager load retiré, relation morte supprimée |
| `top-absences` : périmètres numérateur/dénominateur différents | absences **négatives**, étudiants comptés absents aux séances d'autres filières | un seul périmètre, par filière |
| `stats_par_ue.filiere_code` toujours `null` | filtre par filière du tableau UE sans aucune option | `ues.filiere_id` ajouté au `SELECT` |
| 404 indiscernables | ressource absente et URL inconnue rendaient le même message | `getPrevious()` inspecté |
| `departmentReport` renvoie du JSON nommé `.pdf` | fichier inouvrable | rendu PDF réel sur `?format=pdf` |
| Export CSV sans aucun filtre | volumétrie sans rapport avec l'écran | filtres transmis |
| `CleanExpiredQrCodes` ne compilait pas | la commande `qr:clean-expired` **n'existait pas** ; sa tâche planifiée échouait en silence | arithmétique retirée de l'interpolation |
| `app/Console/Kernel.php` mort depuis Laravel 11 | `qr:clean-expired` et `ecs:sync-statut` **ne tournaient pas du tout** — les statuts EC/UE n'étaient jamais recalculés | tâches rapatriées dans `routes/console.php`, fichier supprimé |
| Modèle de CSV téléchargé avec le jeton en paramètre d'URL | 401 systématique, et jeton exposé dans l'historique et les journaux | passage par le client axios authentifié |
| `AlertService`, `SmsService`, `AlertSetting` | vestiges visant une classe `Member` inexistante et un paquet absent de `composer.json` | supprimés |

### Prérequis d'outillage

| Réf. | Objet | État |
|---|---|---|
| O1 | Couverture frontend | ✅ `@vitest/coverage-v8`, seuils en cliquet, `npm run test:coverage` |
| O2 | MSW | ✅ `src/test/msw/` + mouchard de requêtes ; 12 tests d'intégration |
| O3 | axe-core | 🔶 `@axe-core/playwright` installé, aucun test écrit |
| O4 | Code mort | ✅ voir B7 |
| O5 | `TestSuiteSeeder` | ❌ |
| O6 | `data-testid` | ❌ |
| O7 | Démarrage du Postgres de test | ✅ `backend/Makefile` (`db-up`, `db-reset`, `test`, `lint`…) |
| O8 | Couverture backend | 🔶 mesurée en CI seulement — ni PCOV ni Xdebug en local |
| O9 | Factories | ✅ 10 factories + `FactoriesTest` (9 tests) |
| O10 | Verrou de migration | 🔶 indexé sur l'empreinte des migrations ; parallélisation non traitée |
| O11–O14 | Harnais mobile | ✅ jest-expo, doubles natifs, 16 tests, couverture en cliquet |
| O15 | Maestro | ❌ |
| — | k6 `LOAD-01` | 🔶 script et générateur versionnés et validés ; **campagne non exécutée** (k6 absent) |
| — | ZAP | 🔶 configuration versionnée ; **passe non exécutée** |
| — | CI (§6.1) | ✅ `.github/workflows/ci.yml` — lint, types, 3 suites, audit |

### Mesures réelles au 2026-08-22

| Couche | Fichiers | Tests | Couverture de lignes |
|---|---:|---:|---|
| Backend | 42 | **280** | non mesurée (pas de pilote local) |
| Frontend | 44 | **223** | **30,80 %** |
| Mobile | 2 | **16** | **6,95 %** |

**L'exigence §2.1.3 du mémoire — couverture ≥ 70 % — n'est pas atteinte.** L'écart
est d'un facteur 2,3 côté frontend. C'est la première fois que ce chiffre est
mesuré ; il n'avait jamais pu l'être, faute d'outil installé.

### Ce qui reste ouvert

1. **Couverture** : porter le frontend de 30 % à 70 % demande d'écrire les tests
   d'intégration de page `FE-I-03` à `FE-I-32`. Les seuils sont en cliquet pour
   interdire la régression en attendant.
2. **Chiffres du chapitre 4** : `LOAD-01` et la passe ZAP sont outillés mais
   **jamais exécutés** ici. Les 187 ms / 394 ms de §4.4 et le tableau OWASP de
   §4.3 restent donc non reproduits.
3. **§4.5 (IA)** : le corpus de 45 documents et ses vérités terrain sont absents
   du dépôt. Rien n'est reproductible sur ce point.
4. **Mémoire** : les incohérences documentaires ci-dessous restent à corriger,
   auxquelles s'ajoutent le texte de B4 et la correction du chiffre de couverture.

---

## 2. Ce qui bloque « tout tester depuis le frontend »

Huit constats vérifiés dans le code. Ils sont listés d'abord parce que trois d'entre eux rendent
certains parcours frontend intestables tant qu'ils ne sont pas tranchés.

### B1 — ✅ CORRIGÉ (2026-08-22) — `scan_challenge` : trois formats incompatibles

**Résolution.** Le défi est désormais **émis par le serveur** et non plus dérivé d'un secret
côté client : `GET /presence/course-by-token/{token}` renvoie
`hash_hmac('sha256', token, app.key)`, que le client réexpédie tel quel. La clé ne quitte plus
le serveur ; lié au jeton, le défi hérite de son usage unique et de son TTL de 60 s.

Le diagnostic initial était d'ailleurs incomplet : `config('app.key')` renvoie la chaîne
littérale `base64:…`, si bien qu'*aucune* formule côté client — pas même celle du serveur
recopiée à l'identique — ne pouvait produire la valeur attendue. Toute tentative d'aligner les
clients sur la formule du serveur supposait de publier l'`APP_KEY` de Laravel dans le bundle,
ce qui n'authentifie rien et expose la clé maîtresse de chiffrement.

Trois tests de contrat ajoutés (`PresenceScanTest`) : défi du serveur accepté, défi fabriqué par
le client refusé, défi d'un autre QR Code refusé. Les cinq helpers qui recalculaient la formule
du serveur sont centralisés dans `TestCase::defiDeScan()`, avec la mention explicite que seule
la paire de tests de contrat prouve le contrat.

<details><summary>Constat d'origine</summary>


| Où | Formule produite / attendue |
|---|---|
| `backend/app/Http/Controllers/Api/PresenceController.php:509` | attend `sha256(device_fingerprint + ':' + APP_KEY)` |
| `frontend/src/services/fingerprint.ts:119` | produit `btoa(visitorId + ':' + timestamp + ':' + nonce)` |
| `mobile-app/src/services/fingerprint.ts:59` | produit `btoa(prefix16 + ':' + timestamp + ':' + nonce)` |

Le contrôleur compare par `hash_equals` : aucun challenge émis par un vrai client ne peut
correspondre. Chaque scan réel part donc en **403 « Échec de la vérification de sécurité »** avec
création d'une `Anomaly` de type `invalid_scan_challenge`.

Pourquoi la suite est verte malgré ça : les tests backend recalculent eux-mêmes la formule du
serveur (`tests/Feature/PresenceScanTest.php:40`, `tests/Feature/TripleFactorScanTest.php:39`,
`tests/Feature/Presence/ScanWindowTest.php:132`). Ils vérifient que le serveur est d'accord avec
lui-même. Le commentaire du client mobile décrit d'ailleurs une vérification (préfixe + fraîcheur
60 s) que le backend ne fait pas.

→ Test manquant : **contrat client↔serveur** (`FE-CT-01`, `MO-CT-01`).
</details>

### B2 — ✅ CORRIGÉ (2026-08-22) — La page ciblée par le QR Code n'envoie pas de `scan_challenge`

**Résolution.** `PresenceValidationPage` lit le défi dans la réponse de `course-by-token`
(déjà appelée au montage) et l'envoie avec le scan, avec le `visitorId` FingerprintJS comme
`device_fingerprint`. `QRValidationPage` suit le même chemin. Côté mobile, `useScan` récupère
le défi via `course-by-token` en parallèle du GPS et du Wi-Fi.

<details><summary>Constat d'origine</summary>


1. Le QR encode `{FRONTEND_URL}/attendance/validate?token=…` (`backend/app/Services/QrCodeImageService.php:30`).
2. Cette route rend `PresenceValidationPage`, qui poste seulement `identifiant_unique`, `token`
   et `device_fingerprint` (`frontend/src/pages/attendance/PresenceValidationPage.jsx:83-87`).
3. Le contrôleur valide `'scan_challenge' => 'required|string'` (`PresenceController.php:86`).

→ **422 systématique** sur le parcours étudiant principal du CDC (§7.4.1). Une seconde page,
`QRValidationPage` (`/attendance/scan`), envoie bien un challenge — mais au mauvais format (B1),
et elle est derrière l'authentification admin.

→ Tests manquants : `FE-SCAN-01` (assertion sur le corps de la requête), `E2E-SCAN-01`.
</details>

### B3 — ✅ CORRIGÉ (2026-08-22) — La validation manuelle des présences n'a aucun écran

**Résolution.** `PresenceQueuePage` est construite et routée sur `/attendance/queue`, ajoutée
aux onglets de `AttendanceLayout` et à la navigation latérale. Elle consomme les trois endpoints
`presence/pending`, `presence/{id}/validate` et `presence/{id}/reject`. `E2E-VAL-01..05` ne sont
plus bloqués.

<details><summary>Constat d'origine</summary>


Le backend expose `GET /admin/presence/pending`, `PATCH /admin/presence/{id}/validate` et
`PATCH /admin/presence/{id}/reject`. Aucun de ces trois endpoints n'est appelé par le frontend.
La route `/attendance/validate`, libellée « Valider » / « Présences » dans la navigation
(`AttendanceLayout.jsx:4`, `SideNavBar.jsx:23`, `BottomNavBar.jsx:7`), est le **formulaire public
de scan**, pas la file de validation.

Or le mémoire §2.2.7 et la Figure 7 décrivent cette fonctionnalité, et le statut `suspect` est bien
produit par le backend (détection d'appareil partagé, `PresenceController.php` §8 bis).

→ Non testable depuis le frontend tant que l'écran n'existe pas. À construire avant d'écrire
`E2E-VAL-01..05`.
</details>

### B4 — ✅ TRANCHÉ (2026-08-22) — Fenêtre de scan : le mémoire et le code divergent

**Décision : le code fait foi, le mémoire est à corriger.**

Trois raisons. La fenêtre du code est strictement plus résistante à la fraude
que le système existe pour empêcher — signer en début de séance puis repartir.
Elle a un point de calcul unique (`Evenement::ouvertureScan()` /
`fermetureScan()`), configurable par variable d'environnement, et couvert par
neuf tests (`ScanWindowTest`). Aligner le code sur le document reviendrait à
**affaiblir le produit pour satisfaire une phrase**.

Vérifié au passage : aucun client ne code la fenêtre en dur. Les valeurs ne
vivent qu'en configuration serveur, la divergence est donc purement documentaire.

Texte de remplacement pour le mémoire §3.2.3 et le CDC §7.3.3 :

> La fenêtre de prise de présence est ancrée sur l'heure de **fin** du cours :
> elle s'ouvre 15 minutes avant celle-ci et se ferme 10 minutes après
> (`PRESENCE_SCAN_AVANT_FIN` et `PRESENCE_SCAN_APRES_FIN`). Ce choix est
> délibéré : une fenêtre ouverte dès le début de séance permettrait à un
> étudiant de valider sa présence puis de quitter le cours. L'ancrage sur la fin
> rend cette fraude par absence partielle inopérante.

<details><summary>Constat d'origine</summary>


| Source | Fenêtre |
|---|---|
| Mémoire §3.2.3 et CDC §7.3.3 | `[heure_debut − 15 min, heure_fin + 15 min]` |
| Code (`backend/config/presence.php`, `Evenement::ouvertureScan()/fermetureScan()`) | `[heure_fin − 15 min, heure_fin + 10 min]` |

Le code est volontairement plus strict (commentaire à l'appui : empêcher de signer en début de
séance puis repartir). Il faut trancher : corriger le mémoire, ou remettre `PRESENCE_SCAN_APRES_FIN`
à 15 et rouvrir la fenêtre au début. Sans décision, les cas `E2E-SCAN-04/05` contredisent le
document de référence.
</details>

### B5 — Fonctionnalités backend sans interface (intestables « depuis le frontend »)

| Endpoint | Référence | Écran |
|---|---|---|
| `GET/POST/DELETE /admin/students/{student}/ecs…` | CDC §7.2.3 (inscriptions étudiant↔EC) | aucun |
| `GET /admin/sessions`, `DELETE /admin/sessions/others` | mémoire §2.1.2 (sessions actives) | aucun ; `SecurityPage` ne fait que profil + mot de passe + 2FA |
| `POST /admin/profile/2fa/verify` | 2FA | aucun |
| `GET /admin/import/csv/template/{type}` | mémoire §3.2.4 (modèles CSV téléchargeables) | seulement `ImportPage`, **non routée** (voir B7) |
| `GET /admin/reports/presence/{id}/pdf` | mémoire §3.3.1 (rapports) | appelé avec un identifiant figé (voir B8) |

### B6 — ✅ CORRIGÉ (2026-08-22) — Endpoint inexistant appelé par le frontend

`frontend/src/pages/settings/SallesPage.jsx:57` appelait `GET /admin/etablissements`. Cette route
n'existe pas dans `routes/api.php` (seule `/super-admin/etablissements` existe) → 404 avalé
silencieusement, entité affichée « Entité non définie » et formulaire jamais prérempli.
Un test d'intégration de page qui assertait les appels sortants l'aurait vu.

**Résolution.** `GET /user` charge désormais la relation `etablissement` — donnée propre de
l'utilisateur — et `SallesPage` s'en sert. Un appel au lieu de deux.

### B7 — ✅ TRANCHÉ (2026-08-22) — Code mort : router ou supprimer

Le verdict diffère par module — et la liste d'origine était partiellement fausse.

| Module | Décision | Motif |
|---|---|---|
| `pages/import/ImportPage.jsx` | **routé** sur `/import` | Seul appelant de `/admin/import/csv/courses` et `/admin/import/csv/schedule`. Contrairement à ce que supposait ce plan, `AcademicSlatePage` (`/schedules/slate`) ne fait **aucun** import : sans cette route, les imports CSV UE/EC et emploi du temps n'avaient aucune interface. |
| `pages/courses/CourseListPage.jsx` | supprimé | Mêmes endpoints que `UEManagementPage`, déjà routée sur `/courses`. |
| `pages/attendance/AttendanceStatsPage.jsx` | supprimé | `/admin/presence/stats` est déjà consommé par `ReportPrintPreview` ; l'historique par `PresenceHistoryPage`. |
| `pages/settings/SettingsPage.jsx` | supprimé | Aucune requête. Coquille de navigation supplantée par `SettingsLayout`. |
| `components/ui/Drawer.jsx` | **conservé** | Classé à tort : `EnrollmentDrawer` l'utilise. |

Défaut trouvé en routant `ImportPage` : son téléchargement de modèle ouvrait
l'URL avec le jeton **en paramètre de requête**. Sanctum ne lit que l'en-tête
`Authorization` — le téléchargement repartait en 401 — et le jeton se retrouvait
dans l'historique du navigateur et les journaux du serveur.

### B8 — ✅ CORRIGÉ (2026-08-22) — Exports de rapports : cible figée, format mensonger, filtres ignorés

Trois défauts, et non un seul — les deux derniers n'avaient pas été vus au premier passage :

1. `FilteredReportsPage.jsx:276` → `'/admin/reports/presence/1/pdf'` : l'export « global »
   portait toujours sur l'événement 1, quel que soit le filtre. Même chose pour le repli
   `/admin/reports/department/1` (`FilteredReportsPage.jsx:284`, `ReportsPage.jsx:426`).
2. `departmentReport` renvoie une **`JsonResponse`**, pas un PDF. Le bouton « Rapport Filière
   PDF » téléchargeait donc du JSON sous un nom en `.pdf` — un fichier qu'aucun lecteur n'ouvre.
3. « Liste Présences CSV » n'envoyait **aucun paramètre** : l'export portait sur toutes les
   présences de l'établissement, quels que soient les filtres affichés (contredit `E2E-REP-03`).

**Résolution.** `departmentReport` rend un vrai PDF sur `?format=pdf` via une vue
`reports/department.blade.php` alignée sur les exports existants ; les deux pages transmettent
les trois filtres honorés par le backend ; le bouton « Rapport Global PDF » est retiré, aucun
endpoint ne produisant de rapport global ; les échecs d'export, jusque-là avalés, affichent un
message.

### Incohérences documentaires à corriger dans le mémoire

| § | Le mémoire dit | Le code dit |
|---|---|---|
| §1.1.4 vs §3.2.4 | « provider par défaut : Groq » / « Gemini (provider par défaut) » | `config/ai.php` → `env('AI_PROVIDER', 'gemini')` |
| §2.4.1 | services `GeofenceService`, `AnomalyDetectionService`, `RegularityScoreService` | `GeolocationService`, `AlertService`, `AttendanceRateService` ; les deux derniers noms cités n'existent pas |
| §2.3 | tables `chat_conversations`, `chat_messages` | supprimées par `2026_07_28_120000_drop_chat_tables.php` |
| §2.2.4 | « 17 entités principales » | 22 modèles Eloquent dans `app/Models/` |
| CDC §9.1 | « protection CSRF sur tous les formulaires » | API Bearer stateless, sans cookie de session : CSRF sans objet |
| §2.4.2 | « bcrypt coût 12 » | `phpunit.xml` force `BCRYPT_ROUNDS=4` en test (normal), à vérifier en prod |

---

## 3. Plan de tests FRONTEND

C'est le plan principal : il vise à couvrir l'ensemble du système **par l'interface**, avec quatre
niveaux qui se répartissent le travail sans se recouvrir.

| Niveau | Outil | Périmètre | Ce qu'on n'y met pas |
|---|---|---|---|
| N1 — Unitaire / composant | Vitest + RTL | rendu, props, états, formatage, hooks purs | appels réseau réels, navigation multi-pages |
| N2 — Intégration page | Vitest + **MSW** | une page + ses appels HTTP simulés, y compris le **corps** des requêtes | rendu pixel, chaîne serveur |
| N3 — E2E navigateur | Playwright (`tests/frontend/`) | parcours complets sur backend réel (local ou prod) | logique de calcul unitaire |
| N4 — Contrat / a11y / perf | Playwright + axe-core + Lighthouse CI | conformité du payload, accessibilité, budget de perf | fonctionnel métier |

### 3.0 Prérequis d'outillage (à faire avant d'écrire des tests)

| # | Action | Commande / fichier |
|---|---|---|
| O1 | Installer la couverture | `npm i -D @vitest/coverage-v8` ; seuils dans `vite.config.js` |
| O2 | Ajouter MSW pour asserter les corps de requête | `npm i -D msw` ; handlers dans `src/test/msw/` |
| O3 | Ajouter axe-core pour l'a11y | `npm i -D @axe-core/playwright` |
| O4 | Router ou supprimer le code mort (B7) | `src/App.jsx` |
| O5 | Extraire un jeu de données de test dédié | seeder `TestSuiteSeeder` distinct de `DemoPresenceSeeder` |
| O6 | Ajouter les `data-testid` sur les éléments critiques | formulaire de scan, DataTable, modales |

Seuils de couverture à viser (objectif §2.1.3 du mémoire) :

```js
// vite.config.js → test.coverage
coverage: {
  provider: 'v8',
  reporter: ['text', 'html', 'json-summary'],
  exclude: ['src/test/**', 'src/main.jsx', '**/*.config.js'],
  thresholds: { lines: 70, functions: 70, branches: 60, statements: 70 },
}
```

### 3.1 N1 — Unitaire et composant

**Déjà couvert (42 fichiers, 211 tests) :** `Button`, `Badge`, `Modal`, `DataTable`, `Pagination`,
`SearchInput`, `Toggle`, `Tabs`, `Toast`, `EmptyState`, `LoadingSkeleton`, `FileDropZone`,
`SkipLink`, `AlertsBanner`, `FloatingActionButton`, `KPICard`, `AttendanceChart`, `BarChart`,
`GaugeChart`, `ProgressBar`, `TopAbsencesChart`, `RecentQRScans`, `TodaysEvents`, `SideNavBar`,
`TopNavBar`, `BottomNavBar`, `ProtectedRoute`, `useApi`, `useDebounce`, `useToast`, `cn`,
`formatters`, `validators`, + `PagesSmoke` (montage de 16 pages).

**À ajouter :**

| ID | Cible | Cas à couvrir | Priorité |
|---|---|---|---|
| FE-U-01 | `hooks/useFingerprint.ts` | `isReady` passe à vrai après résolution ; `createScanChallenge()` renvoie `{challenge, visitorId}` ; échec FingerprintJS → repli sans exception | **P0** |
| FE-U-02 | `services/fingerprint.ts` | format exact du challenge (base64 décodable, 3 segments) ; stabilité du `visitorId` entre deux appels | **P0** |
| FE-U-03 | `api/cache.js` | `ttlFor()` par préfixe ; `invalidateApiCache(prefix)` ne vide que le préfixe ; `invalidateApiCache()` vide tout | P1 |
| FE-U-04 | `api/axios.js` | injection du Bearer ; 401 → purge `auth_token` + `presence_user` + redirection ; toute écriture non-GET → cache vidé | **P0** |
| FE-U-05 | `context/AuthContext.jsx` | `login()` accepte les deux formes de réponse (`data.data` / racine) ; entrée `presence_user` illisible → purge du token ; `logout()` vide le cache | **P0** |
| FE-U-06 | `components/layout/MainLayout` | rendu de l'`Outlet`, `SideNavBar` en ≥ lg, `BottomNavBar` en < lg | P2 |
| FE-U-07 | `components/layout/SuperAdminLayout` + `SuperAdminSidebar` | items de navigation, item actif | P2 |
| FE-U-08 | `components/layout/AttendanceLayout` / `SettingsLayout` | onglets, redirection d'index | P2 |
| FE-U-09 | `utils/assets.js` | résolution des chemins d'images, absence d'import cassé | P2 |
| FE-U-10 | `ProtectedRoute` (compléments) | `role="super_admin"` avec un `faculte_admin` → redirection `/dashboard` ; et l'inverse | P1 |

### 3.2 N2 — Intégration page (MSW)

Le point clé : les tests actuels remplacent le module `api/axios` par un double
(`vi.mock('../api/axios')`) qui répond toujours `{success:true,data:[]}`. Ils prouvent que la page
monte, jamais **ce qu'elle envoie**. C'est exactement l'angle mort qui a laissé passer B2 et B6.
MSW intercepte au niveau HTTP : on peut asserter méthode, URL, query et corps.

| ID | Page | Ce qu'on vérifie | Priorité |
|---|---|---|---|
| FE-I-01 | `PresenceValidationPage` | `GET /presence/course-by-token/{token}` appelé une fois ; le corps du `POST /presence/scan` contient `identifiant_unique`, `token`, `device_fingerprint` **et** `scan_challenge` (échoue aujourd'hui — B2) | **P0** |
| FE-I-02 | `PresenceValidationPage` | mapping des statuts → messages : 410 « Session expirée », 409 « déjà validée », 403 message serveur, 404 « Identifiant inconnu », 422 → message de validation | **P0** |
| FE-I-03 | `PresenceValidationPage` | token absent de l'URL → état `idle`, pas d'appel réseau | P1 |
| FE-I-04 | `QRValidationPage` | challenge présent dans le payload ; attente de `isReady` avant envoi | **P0** |
| FE-I-05 | `LoginPage` | email invalide → pas d'appel réseau ; 422 → premier message de `errors` affiché ; succès `super_admin` → `/super-admin`, sinon `/dashboard` | **P0** |
| FE-I-06 | `DashboardPage` | 4 appels (`/admin/dashboard`, `attendance-trend`, `top-absences`, `today-events`) ; un échec sur un seul n'empêche pas le rendu des autres | P1 |
| FE-I-07 | `StudentManagementPage` | pagination (`page`, `per_page`), recherche débouncée (un seul appel), création → `POST /admin/students` avec le corps saisi, suppression → confirmation puis `DELETE` | **P0** |
| FE-I-08 | `StudentManagementPage` | `POST /admin/students/promote` : corps, confirmation, rafraîchissement de la liste | P1 |
| FE-I-09 | `PresenceHistoryPage` | filtres → query ; export → `GET /admin/presence/export` en `responseType: blob` ; changement rapide de filtre → une seule réponse retenue | **P0** |
| FE-I-10 | `AnomaliesListPage` | `GET /admin/alerts` ; `POST /admin/alerts/{id}/resolve` retire la ligne | P1 |
| FE-I-11 | `EvenementManagementPage` | CRUD événement ; `GET /admin/qrcode/{id}/generate` ; affichage du compte à rebours ; régénération avant expiration | **P0** |
| FE-I-12 | `UEManagementPage` | CRUD UE et EC, propagation du statut UE→EC | P1 |
| FE-I-13 | `WeeklySchedulePage` | `GET /admin/evenements/creneaux-emploi-du-temps` ; grille par jour/heure ; créneau vide | P1 |
| FE-I-14 | `SallesPage` | CRUD salle avec GPS + SSID/BSSID + rayon ; `hors_reseau` désactive les champs Wi-Fi ; **l'appel `/admin/etablissements` doit disparaître** (B6) | **P0** |
| FE-I-15 | `AcademicYearsPage` | activation d'une année (`PATCH …/activate`) ; une seule active ; `POST /admin/filieres/reconduire` avec `source_annee_id` + `target_annee_id` | P1 |
| FE-I-16 | `FilieresPage` + `CreateFilierePage` | création → rattachement automatique à l'année active (mémoire §2.3.1) | P1 |
| FE-I-17 | `SecurityPage` | changement de mot de passe (erreurs de validation) ; cycle 2FA `enable → confirm → disable` | **P0** |
| FE-I-18 | `ProfilePage` | `GET/PUT /admin/profile` ; champs en lecture seule | P2 |
| FE-I-19 | `NotificationsPage` | liste, `unread-count`, marquer lu / tout lu, suppression | P2 |
| FE-I-20 | `AIAnalysisProgressPage` | interrogation de `analysis-status/{id}` ; arrêt du polling au démontage ; état `failed` | **P0** |
| FE-I-21 | `ScheduleValidationPage` / `CourseValidationPage` | édition des lignes extraites puis `POST /admin/import/validate-events` / `validate-courses` ; refus si score de confiance < 0,70 (CDC §8.3) | **P0** |
| FE-I-22 | `ReportsPage` / `FilteredReportsPage` | construction des query par filtre ; **export PDF avec l'identifiant réel** (B8) ; état vide | **P0** |
| FE-I-23 | `ExcelExportPage` | `GET /admin/reports/excel/export` en blob, nom de fichier | P2 |
| FE-I-24 | `SemesterComparison` / `ProgramComparison` / `AcademicYearComparison` | appels en parallèle, agrégation, division par zéro | P1 |
| FE-I-25 | `TicketsListPage` / `TicketDetailPage` / `ContactFormPage` | création, réponse, changement de statut, suppression | P2 |
| FE-I-26 | `SuperAdminDashboardPage` | `GET /super-admin/dashboard` | P1 |
| FE-I-27 | `EtablissementManagementPage` + `CreateEtablissementPage` + `EtablissementDetailPage` | CRUD, `stats`, `resend-credentials` | P1 |
| FE-I-28 | `BulkImportPage` | `POST /super-admin/etablissements/import`, rapport d'erreurs ligne par ligne | P1 |
| FE-I-29 | `LandingPage` | `GET /landing/stats` sans token ; valeurs de repli si l'appel échoue | P2 |
| FE-I-30 | `ForgotPasswordPage` / `ResetPasswordPage` | `POST /forgot-password`, `POST /reset-password` ; 429 (throttle 6/min) → message dédié | P1 |
| FE-I-31 | `StudentStatsPage` | `GET /admin/students/{id}/stats`, étudiant sans présence | P2 |
| FE-I-32 | `AcademicSlatePage` | import CSV UE/EC et emploi du temps (`/admin/import/csv/*`), téléchargement de modèle | P1 |

### 3.3 N3 — E2E navigateur (Playwright)

Environnement : `frontend-local` (Vite `:5173` + `php artisan serve :8000` + Postgres de test seedé
par `TestSuiteSeeder`). La cible `frontend-production` est réservée à une campagne de fumée
(`@smoke`), pas aux tests qui écrivent en base.

Le fichier existant `tests/frontend/login.spec.ts` est à réécrire : ses assertions sont
conditionnelles (`if (await btn.isVisible())`) et il passe même si rien ne se produit.

#### Authentification et accès

| ID | Cas | Étapes | Attendu | Prio |
|---|---|---|---|---|
| E2E-AUTH-01 | Connexion admin faculté | `/login` → email + mot de passe → Se connecter | redirection `/dashboard`, nom affiché dans la barre | **P0** |
| E2E-AUTH-02 | Connexion super admin | idem avec le compte super admin | redirection `/super-admin` | **P0** |
| E2E-AUTH-03 | Identifiants invalides | mot de passe erroné | message d'erreur, reste sur `/login`, aucun token en `localStorage` | **P0** |
| E2E-AUTH-04 | Champs vides | soumettre à blanc | « Veuillez remplir tous les champs », aucun appel réseau | P1 |
| E2E-AUTH-05 | Email malformé | `abc@` | « adresse email valide », aucun appel réseau | P1 |
| E2E-AUTH-06 | Rate limiting | 6 tentatives en < 1 min | 429 et message dédié (CDC §9.1) | **P0** |
| E2E-AUTH-07 | Cloisonnement de rôle | admin faculté → `/super-admin` à la main | redirection `/dashboard` | **P0** |
| E2E-AUTH-08 | Route protégée sans session | `localStorage` vidé → `/students` | redirection `/login` | **P0** |
| E2E-AUTH-09 | Expiration de session | token révoqué côté serveur puis navigation | 401 → purge + redirection `/login` | **P0** |
| E2E-AUTH-10 | Déconnexion | menu → Déconnexion | `POST /logout`, `localStorage` vide, retour `/login` | P1 |
| E2E-AUTH-11 | Mot de passe oublié | `/forgot-password` → email connu | message de confirmation ; mail capté (Mailpit) | P1 |
| E2E-AUTH-12 | Réinitialisation | lien du mail → nouveau mot de passe → connexion | connexion réussie avec le nouveau mot de passe | P1 |
| E2E-AUTH-13 | 2FA | activer en `/settings/security`, se déconnecter, se reconnecter | code demandé ; code faux refusé ; code bon accepté | P1 |
| E2E-AUTH-14 | Changement de mot de passe obligatoire | compte `must_change_password` | toute route admin renvoie vers le changement (middleware `password.changed`) | P1 |

#### Parcours étudiant — scan (cœur du système, H1)

| ID | Cas | Étapes | Attendu | Prio |
|---|---|---|---|---|
| E2E-SCAN-01 | Scan valide | admin génère le QR sur `/schedules/events` → ouvrir l'URL du QR en contexte anonyme → saisir l'identifiant unique | écran « Présence validée », `presences` +1 en `valide` | **P0** |
| E2E-SCAN-02 | Token déjà consommé | rejouer la même URL | 410 → « Session expirée. Veuillez scanner un nouveau QR code. » | **P0** |
| E2E-SCAN-03 | Token expiré (TTL 60 s) | attendre > 60 s puis soumettre | 410, message identique | **P0** |
| E2E-SCAN-04 | Avant l'ouverture de la fenêtre | événement dont `fin − 15 min` est dans le futur | 403 « pas encore ouverte », heure d'ouverture annoncée | **P0** |
| E2E-SCAN-05 | Après la fermeture | événement dont `fin + 10 min` est passé | 403 « terminée depuis HH:MM » | **P0** |
| E2E-SCAN-06 | Identifiant inconnu | identifiant bidon | 404 + rappel du format `NOM_PRENOM_MATRICULE_FILIERE_ANNEE` | **P0** |
| E2E-SCAN-07 | Étudiant non inscrit à l'EC | scanner un EC non inscrit (`DEMO-EC-ALGB`) | 403 « Étudiant non inscrit à ce cours » | **P0** |
| E2E-SCAN-08 | Doublon, même appareil | rescanner après succès | 409 « Présence déjà enregistrée » | **P0** |
| E2E-SCAN-09 | Doublon, appareil différent | second navigateur, même étudiant | 409 « Alerte fraude » + `Anomaly` `double_scan_device_mismatch` | **P0** |
| E2E-SCAN-10 | Appareil partagé | deux étudiants, même empreinte, même événement | 2ᵉ présence en `suspect` + `Anomaly` `appareil_partage` | **P0** |
| E2E-SCAN-11 | Hors géorepérage | position simulée à > rayon (`context.setGeolocation`) | 403 avec distance et rayon dans le message | **P0** |
| E2E-SCAN-12 | Dans le géorepérage | position dans le rayon | succès, `verification.gps_valide = true` | **P0** |
| E2E-SCAN-13 | Salle sans GPS configuré | salle `latitude` nulle | succès, `gps_valide = 'non_config'` | P1 |
| E2E-SCAN-14 | Wi-Fi non conforme | SSID différent de `ssid_attendu` | 403 « Réseau Wi-Fi non conforme » | P1 |
| E2E-SCAN-15 | Salle `hors_reseau` | salle en mode hors-réseau | succès sans contrôle Wi-Fi | P1 |
| E2E-SCAN-16 | Salle inactive | `actif = false` | mode basique (QR seul), succès | P2 |
| E2E-SCAN-17 | Régénération après scan | comparer le token avant/après un scan réussi | nouveau token actif immédiatement (CDC §9.2.1) | **P0** |
| E2E-SCAN-18 | Throttle du scan | 4 soumissions en < 1 min depuis la même IP | 429 (`throttle:scan-presence`) | P1 |
| E2E-SCAN-19 | Token malformé | `?token=abc` | message « QR Code invalide ou expiré », pas d'écran blanc | P1 |
| E2E-SCAN-20 | Sans token | `/attendance/validate` nu | état d'attente, pas d'appel réseau | P2 |

#### Étudiants

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| E2E-STU-01 | Créer un étudiant | apparaît dans la liste, identifiant unique généré au format déterministe (CDC §7.1.3) | **P0** |
| E2E-STU-02 | Doublon d'email / matricule | erreur de validation affichée sur le champ | **P0** |
| E2E-STU-03 | Modifier un étudiant | valeurs persistées après rechargement | P1 |
| E2E-STU-04 | Supprimer | confirmation obligatoire ; refus si présences liées (garde de cascade) | **P0** |
| E2E-STU-05 | Recherche | filtre appliqué, un seul appel après debounce | P1 |
| E2E-STU-06 | Pagination | page 2 puis retour, total cohérent | P1 |
| E2E-STU-07 | Import CSV valide | `tests-students-valides.csv` → n créés, mails d'identification en file | **P0** |
| E2E-STU-08 | Import CSV avec erreurs | `tests-students-erreurs.csv` → rapport ligne par ligne, aucun enregistrement partiel | **P0** |
| E2E-STU-09 | Promotion de promotion | `promote` → `annee_id` incrémenté pour la sélection | P1 |
| E2E-STU-10 | Statistiques d'un étudiant | `/attendance/student-stats/:id` → taux, historique | P1 |
| E2E-STU-11 | Cloisonnement établissement | admin de l'établissement A ne voit aucun étudiant de B | **P0** |

#### Cours, emplois du temps, événements

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| E2E-CRS-01 | Créer une UE puis un EC | hiérarchie Filière → UE → EC visible | **P0** |
| E2E-CRS-02 | Désactiver une UE | ses EC suivent (observateurs `UeObserver`/`EcObserver`) | P1 |
| E2E-CRS-03 | Supprimer une UE avec EC | refus ou cascade explicite, jamais silencieuse | **P0** |
| E2E-CRS-04 | Créer un événement | date, heures, salle, EC ; visible dans la semaine | **P0** |
| E2E-CRS-05 | Conflit de salle | deux événements qui se chevauchent dans la même salle | refus avec message explicite | **P0** |
| E2E-CRS-06 | Événement hors année active | refus | P1 |
| E2E-CRS-07 | Grille hebdomadaire | `/schedules/weekly` place les créneaux au bon jour/heure | P1 |
| E2E-CRS-08 | Générer le QR d'un événement | image affichée, compte à rebours, rafraîchissement auto avant 60 s | **P0** |
| E2E-CRS-09 | Import CSV UE/EC | `/schedules/slate` → structure créée, doublons détectés | **P0** |
| E2E-CRS-10 | Import CSV emploi du temps | conflits de salle et chevauchements signalés | **P0** |
| E2E-CRS-11 | Télécharger un modèle CSV | fichier reçu, en-têtes conformes | P1 |

#### Import assisté par IA (H2)

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| E2E-IA-01 | Import d'un emploi du temps PDF | analyse asynchrone, page de progression, statut final `completed` | **P0** |
| E2E-IA-02 | Validation humaine obligatoire | rien n'est écrit en base avant confirmation explicite (CDC §8.3) | **P0** |
| E2E-IA-03 | Score de confiance < 0,70 | avertissement et validation forcée ligne par ligne | **P0** |
| E2E-IA-04 | Correction d'une ligne extraite | la valeur corrigée est celle enregistrée | **P0** |
| E2E-IA-05 | PDF illisible / non-emploi du temps | statut `failed`, message actionnable, aucune écriture | P1 |
| E2E-IA-06 | Quota provider dépassé (429) | tâche remise en attente, message explicite (CDC §8.4) | P1 |
| E2E-IA-07 | Import d'une maquette pédagogique | UE/EC proposés puis validés | **P0** |
| E2E-IA-08 | Abandon en cours d'analyse | quitter la page → pas de fuite de polling, reprise correcte au retour | P1 |

#### Validation manuelle (bloqué par B3)

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| E2E-VAL-01 | File des présences suspectes | les `suspect` de `E2E-SCAN-10` apparaissent | **P0** |
| E2E-VAL-02 | Valider une présence | statut `valide`, `validated_by`/`validated_at` renseignés | **P0** |
| E2E-VAL-03 | Rejeter avec motif | statut `rejete`, motif conservé | **P0** |
| E2E-VAL-04 | Motif obligatoire au rejet | refus sans motif | P1 |
| E2E-VAL-05 | Traçabilité | l'action apparaît dans l'`audit_logs` | P1 |

#### Anomalies et alertes

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| E2E-ANO-01 | Liste des anomalies | les anomalies produites par `E2E-SCAN-09/10/11` sont listées avec leur sévérité | **P0** |
| E2E-ANO-02 | Résoudre une anomalie | disparaît de la liste des actives | P1 |
| E2E-ANO-03 | Cloisonnement | aucune anomalie d'un autre établissement | **P0** |

#### Rapports et statistiques

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| E2E-REP-01 | Tableau de bord | 4 KPI cohérents avec la base, graphique de tendance, événements du jour | **P0** |
| E2E-REP-02 | Historique des présences | filtres date/filière/EC/statut cumulés | **P0** |
| E2E-REP-03 | Export CSV de l'historique | fichier téléchargé, colonnes et volumétrie conformes au filtre | **P0** |
| E2E-REP-04 | Export PDF d'une feuille de présence | **le PDF porte sur l'événement sélectionné** (B8) | **P0** |
| E2E-REP-05 | Export Excel | fichier ouvrable, une ligne par présence | P1 |
| E2E-REP-06 | Rapport par filière | taux calculé = présences / attendus | **P0** |
| E2E-REP-07 | Comparaison de semestres | deux colonnes, écart signé | P1 |
| E2E-REP-08 | Comparaison de filières | classement cohérent | P1 |
| E2E-REP-09 | Comparaison d'années | agrégation multi-appels sans race condition | P1 |
| E2E-REP-10 | Aperçu avant impression | `/reports/print/:id` rend une mise en page imprimable | P2 |
| E2E-REP-11 | Filtres sans résultat | état vide explicite, pas de graphique fantôme | P1 |
| E2E-REP-12 | Division par zéro | filière sans étudiant → 0 %, pas de `NaN` | **P0** |

#### Paramétrage

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| E2E-SET-01 | Créer une année académique | apparaît dans la liste | P1 |
| E2E-SET-02 | Activer une année | une seule active à la fois | **P0** |
| E2E-SET-03 | Reconduire les filières | filières de l'année source rattachées à la cible, sans doublon si rejoué | **P0** |
| E2E-SET-04 | Créer une filière | rattachement automatique à l'année active | **P0** |
| E2E-SET-05 | Créer une salle avec GPS + Wi-Fi | valeurs persistées, rayon appliqué au scan | **P0** |
| E2E-SET-06 | Salle en mode hors-réseau | champs Wi-Fi neutralisés | P1 |
| E2E-SET-07 | Supprimer une salle utilisée | refus ou détachement explicite | P1 |
| E2E-SET-08 | Coordonnées GPS invalides | validation côté formulaire (lat ±90, lon ±180) | P1 |

#### Super administration

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| E2E-SA-01 | Tableau de bord global | totaux tous établissements confondus | P1 |
| E2E-SA-02 | Créer un établissement | admin créé, mail de bienvenue avec identifiants | **P0** |
| E2E-SA-03 | Détail + statistiques | chiffres de l'établissement seul | P1 |
| E2E-SA-04 | Renvoyer les identifiants | nouveau mail, mot de passe temporaire | P1 |
| E2E-SA-05 | Modifier / supprimer | persistance ; garde si données rattachées | P1 |
| E2E-SA-06 | Import en masse d'établissements | rapport ligne par ligne | P1 |
| E2E-SA-07 | Un admin faculté ne voit pas `/super-admin` | redirection | **P0** |

#### Support, profil, notifications

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| E2E-SUP-01 | Créer un ticket | visible dans la liste, statut initial | P2 |
| E2E-SUP-02 | Répondre à un ticket | message ajouté au fil | P2 |
| E2E-SUP-03 | Changer le statut | persistance | P2 |
| E2E-SUP-04 | Cloisonnement des tickets | aucun ticket d'un autre établissement | P1 |
| E2E-SUP-05 | FAQ / centre d'aide | navigation catégorie → article sans 404 | P2 |
| E2E-SUP-06 | Notifications | badge non-lus, marquer lu, tout lu, suppression | P2 |
| E2E-SUP-07 | Profil | modification du nom, changement de mot de passe | P1 |

#### Robustesse et transverse

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| E2E-ROB-01 | Backend indisponible | message d'erreur exploitable sur chaque page, pas d'écran blanc | **P0** |
| E2E-ROB-02 | Réponse lente (> 10 s) | indicateur de chargement, pas de double soumission | P1 |
| E2E-ROB-03 | URL inconnue | `NotFoundPage` avec retour au tableau de bord | P2 |
| E2E-ROB-04 | Navigation rapide entre pages | pas d'avertissement React « setState after unmount » (annulation des requêtes) | P1 |
| E2E-ROB-05 | Deux onglets, deux comptes | le cache mémoire ne fuit pas d'un compte à l'autre | **P0** |
| E2E-ROB-06 | Rechargement dur sur route profonde | la page se restaure (SPA fallback Vercel) | P1 |
| E2E-ROB-07 | Retour navigateur après connexion | ne ramène pas sur `/login` connecté | P2 |

### 3.4 N4 — Contrat, accessibilité, performance, visuel

| ID | Cas | Outil | Attendu | Prio |
|---|---|---|---|---|
| FE-CT-01 | Contrat `POST /presence/scan` | Playwright API + client réel | le payload produit par `frontend/src/services/fingerprint.ts` est **accepté** par le backend (échoue aujourd'hui — B1/B2) | **P0** |
| FE-CT-02 | Contrat de réponse | schéma JSON | toutes les réponses admin suivent `{success, message, data, meta?}` | P1 |
| FE-CT-03 | Aucun endpoint fantôme | script | chaque URL littérale du frontend existe dans `routes/api.php` (détecte B6) | **P0** |
| FE-CT-04 | Aucun endpoint orphelin | script | chaque route `admin/*` est consommée, sinon documentée comme API pure | P1 |
| FE-A11Y-01 | axe-core sur 12 pages clés | `@axe-core/playwright` | 0 violation critique/sérieuse | P1 |
| FE-A11Y-02 | Navigation clavier | Playwright | formulaire de scan et de connexion utilisables sans souris ; `SkipLink` fonctionnel | P1 |
| FE-A11Y-03 | Contraste | axe-core | conforme AA sur les deux thèmes | P2 |
| FE-PERF-01 | Budget Lighthouse | Lighthouse CI | LCP < 2,5 s, TBT < 300 ms sur `/login` et `/dashboard` | P2 |
| FE-PERF-02 | Poids des lots | `vite build --report` | aucun chunk > 300 kB gzip ; le découpage `lazy()` est effectif | P2 |
| FE-RESP-01 | Points de rupture | Playwright, 3 tailles (360 / 768 / 1920) | `BottomNavBar` en mobile, `SideNavBar` en desktop, tableaux défilables | P1 |
| FE-VIS-01 | Non-régression visuelle | `toHaveScreenshot()` sur 8 écrans | pas de dérive au-delà du seuil | P2 |

---

## 4. Plan de tests BACKEND

### 4.0 Prérequis

| # | Action |
|---|---|
| O7 | Documenter le démarrage du Postgres de test dans un `Makefile` ou `composer test` : `docker run -d --name uac-test-pg -p 55433:5432 -e POSTGRES_PASSWORD=… -e POSTGRES_DB=…` |
| O8 | Activer la couverture : `php artisan test --coverage --min=70` (requiert Xdebug ou PCOV) |
| O9 | Créer les factories manquantes — il n'existe que `UserFactory` : `Etudiant`, `Filiere`, `AnneeAcademique`, `Ue`, `Ec`, `Salle`, `Evenement`, `QrCode`, `Presence`, `Etablissement` |
| O10 | Remplacer le verrou de migration par fichier (`/tmp/uac_migrations_run_lock`, `TestCase.php:106`) par `RefreshDatabase` ou une base par worker — ce verrou empêche la parallélisation et masque les migrations nouvelles |

### 4.1 Déjà couvert (159 tests)

Scan (`PresenceScanTest`, `ScanWindowTest`, `TripleFactorScanTest`, `SharedDeviceFraudTest`,
`DelegueQrCodeTest`), CRUD étudiants/salles/événements/UE-EC, promotion, historique, imports IA,
cloisonnement établissement, scoping anomalies/notifications/tickets, auth (mot de passe, 2FA,
token étudiant, sessions, changement obligatoire), profil, taux de présence, conflits de salle,
créneaux, gardes de cascade, services `Geolocation` / `Identifiant` / distance salle.

### 4.2 Trous de couverture identifiés

Aucun test ne référence ces endpoints :

| ID | Cible | Cas à écrire | Prio |
|---|---|---|---|
| BE-REP-01..07 | `ReportController` (595 lignes, 7 méthodes) | `exportPdf` (PDF non vide, en-têtes), `departmentReport`, `semesterReport`, `semesterComparison`, `filiereStats`, `filteredStats` (chaque combinaison de filtre), `excelExport` | **P0** |
| BE-CSV-01..06 | `CsvImportController` (570 lignes) | UE/EC : nominal, doublons, colonnes manquantes, encodage ; emploi du temps : conflit de salle, chevauchement ; `downloadTemplate` pour chaque type | **P0** |
| BE-DSH-01..04 | `Admin\DashboardController` | `index`, `attendanceTrend`, `topAbsences`, `todayEvents` — valeurs, cloisonnement, base vide | **P0** |
| BE-SA-01..07 | `SuperAdmin\EtablissementController` | CRUD, `stats`, `resendCredentials` (mail en file), refus si rôle ≠ `super_admin` | **P0** |
| BE-SA-08 | `SuperAdmin\DashboardController` | agrégation multi-établissements | P1 |
| BE-SA-09 | `BulkRegistrationController` | import en masse, transaction, rapport d'erreurs | P1 |
| BE-ALT-01..02 | `AlertController` | `index`, `resolve` (le scoping est testé, pas le comportement) | P1 |
| BE-LND-01 | `LandingPageController` | statistiques publiques sans authentification, aucune donnée nominative exposée | **P0** |
| BE-FIL-01..03 | `FiliereController` | CRUD + `reconduire` (`syncWithoutDetaching`, idempotence), rattachement auto à l'année active | **P0** |
| BE-PWD-01..04 | `PasswordResetLinkController`, `NewPasswordController` | envoi du lien, token invalide, token expiré, throttle 6/min | **P0** |
| BE-ENR-01..05 | `EnrollmentController` | `index`, `available`, `store`, `destroy`, `reset` — avec `annee_id` du pivot | **P0** |
| BE-SES-01..02 | `SessionController` | liste des sessions, révocation des autres | P1 |
| BE-NOT-01..05 | `NotificationController` | liste, compteur, marquer lu, tout lu, suppression | P2 |
| BE-DOC-01 | `ApiDocumentationController` | `/api/docs/json` est un OpenAPI valide et à jour des routes réelles | P1 |
| BE-QRC-01..03 | `QrCodeController` | invalidation du token précédent, expiration plafonnée à `fermetureScan()`, refus hors fenêtre | **P0** |
| BE-CMD-01..07 | Commandes Artisan | `AutoGenerateQrCode`, `CleanExpiredQrCodes`, `GenerateEventsFromSchedule`, `PromoteStudents`, `SyncEcStatus`, `EnrollExistingStudents` — chacune testée en isolation | **P0** |
| BE-JOB-01..05 | Jobs | `AnalysePdfJob`, `ProcessAiImportJob`, `SendIdentifiantEmailJob` — succès, échec, rejeu | P1 |
| BE-SRV-01..05 | Services non testés | `AiAnalysisService` (bascule de provider, score < 0,70), `GeminiProvider`/`GroqProvider`/`OpenRouterProvider` (réponses simulées, 429), `SemesterService`, `ScheduleSlotResolver`, `SmsService` | **P0** |
| BE-MID-01..06 | Middlewares | `ScopeByEtablissement`, `CheckRole`, `EnsurePasswordChanged`, `SecurityHeaders`, `ForceHttps`, `CheckDatabaseConnection` — testés directement, pas seulement par ricochet | **P0** |
| BE-CT-01 | Contrat de scan | un test qui construit le challenge **comme le client** et non comme le serveur (corrige l'angle mort B1) | **P0** |
| BE-INT-01 | Contrainte d'unicité | insertion concurrente `(etudiant_id, evenement_id)` → l'unicité SQL tient, pas seulement la vérification applicative (CDC §9.2.3) | **P0** |
| BE-AUD-01 | Journal d'audit | chaque action sensible (import, validation manuelle, dérogation) écrit dans `audit_logs` | P1 |

### 4.3 Sécurité (OWASP Top 10 2021 — rendre §4.3 reproductible)

| ID | Catégorie | Test à écrire | Prio |
|---|---|---|---|
| SEC-A01-01 | Broken Access Control | pour chaque route `admin/*` : sans token → 401 ; token étudiant → 403 ; token d'un autre établissement → 404/403. À générer depuis `routes/api.php`, pas à la main | **P0** |
| SEC-A01-02 | IDOR | accès direct à un `id` d'un autre établissement sur chaque `apiResource` | **P0** |
| SEC-A02-01 | Crypto | `BCRYPT_ROUNDS=12` en production ; aucun mot de passe en clair dans les logs | **P0** |
| SEC-A03-01 | Injection SQL | charges utiles dans tous les paramètres de filtre et de tri | **P0** |
| SEC-A03-02 | XSS stocké | `<script>` dans nom d'étudiant, intitulé d'UE, message de ticket → restitué échappé | **P0** |
| SEC-A04-01 | Insecure design | scan hors fenêtre / token rejoué / doublon — déjà couverts, à rassembler dans une suite `Security` | P1 |
| SEC-A05-01 | Misconfiguration | `APP_DEBUG=false` en prod ; en-têtes `SecurityHeaders` présents ; `/api/docs` fermé ou en lecture seule en prod | **P0** |
| SEC-A06-01 | Composants vulnérables | `composer audit` + `npm audit --production` en CI, échec sur `high` | **P0** |
| SEC-A07-01 | Auth failures | throttle login 5/min, `student-login`, `scan-presence`, `throttle:api` — chacun vérifié | **P0** |
| SEC-A08-01 | Intégrité | `composer.lock` et `package-lock.json` versionnés et vérifiés en CI | P1 |
| SEC-A09-01 | Journalisation | toute tentative refusée (403/429) laisse une trace exploitable | P1 |
| SEC-A10-01 | SSRF | les URL des providers IA viennent de la configuration, jamais d'une entrée utilisateur | P1 |
| SEC-RGPD-01 | Données personnelles | `/landing/stats` et `/api/docs` n'exposent aucune donnée nominative ; le `device_fingerprint` reste pseudonyme (CDC §9.4) | **P0** |

Complément outillé : une passe **OWASP ZAP baseline** en CI contre l'environnement de test,
configuration à versionner dans `tests/security/zap-baseline.conf`.

### 4.4 Charge (H3 — rendre §4.4 reproductible)

Script à créer : `tests/load/scan.k6.js`.

| ID | Scénario | Objectif | Prio |
|---|---|---|---|
| LOAD-01 | 500 VUs en montée sur `POST /presence/scan` | p50 < 500 ms, p95 < 500 ms, p99 < 1000 ms, erreurs < 1 % | **P0** |
| LOAD-02 | 500 VUs sur `GET /admin/dashboard` | p95 < 500 ms | P1 |
| LOAD-03 | 200 VUs sur `GET /admin/presence/history?per_page=50` | p95 < 800 ms, détection de requêtes N+1 | **P0** |
| LOAD-04 | Palier soutenu 15 min à 100 VUs | pas de dérive mémoire, pas de saturation du pool de connexions | P1 |
| LOAD-05 | Génération de QR en rafale | la régénération après scan ne crée pas de tokens actifs concurrents | **P0** |

Point de méthode à écrire dans le mémoire : chaque scan invalide son token et en régénère un.
Un test de charge naïf sur un seul événement mesure surtout des 410. Le script doit donc
pré-générer un événement et un token par VU, ou mesurer séparément le chemin nominal et le
chemin de rejet — sans quoi les 187 ms / 394 ms annoncés ne décrivent pas le parcours réel.

### 4.5 Évaluation du module IA (H2 — rendre §4.5 reproductible)

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| IA-01 | Corpus de 45 PDF versionné (ou décrit et réductible) | `tests/fixtures/ia/` + vérités terrain en JSON | **P0** |
| IA-02 | Taux d'extraction correcte par type (TEC) | reproduit les 4 lignes du tableau 6 | **P0** |
| IA-03 | Taux de faux positifs (TFP) | ≤ 2 % moyen | **P0** |
| IA-04 | Score de confiance moyen (SCM) | ≥ 0,84 | P1 |
| IA-05 | Bascule de provider | `AI_PROVIDER=groq` puis `openrouter` : même contrat `AnalysisResult` | **P0** |
| IA-06 | Indisponibilité du provider | 429/500 → repli ou mise en attente, jamais d'écriture partielle | **P0** |
| IA-07 | Réponse non-JSON du modèle | erreur propre, `AnalysisResult.status = failed` | **P0** |

---

## 5. Plan de tests MOBILE

Aucun test n'existe. Tout est à monter.

### 5.0 Prérequis

| # | Action |
|---|---|
| O11 | `npx expo install -- --save-dev jest-expo jest @testing-library/react-native react-test-renderer` |
| O12 | `jest.config.js` avec `preset: 'jest-expo'`, `transformIgnorePatterns` pour `expo-*`, `nativewind`, `lucide-react-native` |
| O13 | Doubles pour `expo-camera`, `expo-location`, `expo-network`, `expo-secure-store`, `expo-crypto`, `react-native-wifi-reborn` |
| O14 | Scripts : `npm run test`, `npm run test:ci`, garder `ts:check` |
| O15 | E2E : **Maestro** (compatible dev build Expo) — flows dans `mobile-app/.maestro/` |

### 5.1 Unitaire et hooks

| ID | Cible | Cas | Prio |
|---|---|---|---|
| MO-U-01 | `services/fingerprint.ts` | empreinte stable entre appels, mise en cache, composants manquants → valeurs de repli | **P0** |
| MO-U-02 | `services/fingerprint.ts` | format du challenge : base64, 3 segments, préfixe de 16 caractères | **P0** |
| MO-U-03 | `services/fingerprint.ts` | `verifyScanChallenge` rejette au-delà de `CHALLENGE_MAX_AGE_SEC` | P1 |
| MO-U-04 | `hooks/useLocation.ts` | permission refusée → `null` sans exception ; timeout GPS ; haute précision demandée | **P0** |
| MO-U-05 | `hooks/useWifi.ts` | permission refusée, Wi-Fi coupé, SSID indisponible sur iOS | **P0** |
| MO-U-06 | `hooks/useScan.ts` | GPS et Wi-Fi collectés **en parallèle** ; payload complet ; `identifiant_unique` absent → refus avant appel réseau | **P0** |
| MO-U-07 | `hooks/useScan.ts` | mapping des erreurs : 409 → toast `warning` « Déjà enregistré », 403 → `error`, message serveur préféré au message axios | **P0** |
| MO-U-08 | `hooks/useScan.ts` | `scanning` repasse à `false` même en cas d'exception | P1 |
| MO-U-09 | `utils/token-storage.ts` | écriture/lecture/effacement via SecureStore ; absence de token | **P0** |
| MO-U-10 | `api/client.ts` | Bearer injecté ; 401 → `clearAuth()` ; timeout de 30 s respecté | **P0** |
| MO-U-11 | `auth/AuthContext.tsx` | `login` stocke token + utilisateur, `logout` purge, restauration au démarrage, `est_responsable` propagé | **P0** |

### 5.2 Composants et écrans

| ID | Cible | Cas | Prio |
|---|---|---|---|
| MO-C-01 | `login.tsx` | champs vides → erreurs locales, aucun appel ; échec → toast ; succès → `router.replace('/(tabs)')` | **P0** |
| MO-C-02 | `(tabs)/_layout.tsx` | l'onglet « QR du cours » est masqué si `est_responsable` est faux, visible sinon | **P0** |
| MO-C-03 | `components/scanner/QRScanner.tsx` | permission caméra refusée → écran d'explication ; cooldown de 2 s entre deux scans ; QR non conforme ignoré | **P0** |
| MO-C-04 | `(tabs)/index.tsx` (Scanner) | scan → appel `submitScan` avec le token extrait ; état de chargement ; réinitialisation après résultat | **P0** |
| MO-C-05 | `(tabs)/qrcode.tsx` | rafraîchissement toutes les 20 s ; 403/404 affiche le message serveur ; la confirmation ne survit pas au changement d'événement | **P0** |
| MO-C-06 | `(tabs)/dashboard.tsx` | statistiques, état vide, erreur réseau | P1 |
| MO-C-07 | `(tabs)/history.tsx` | pagination, liste vide, `pull-to-refresh` | P1 |
| MO-C-08 | `(tabs)/profile.tsx` | données affichées, déconnexion | P1 |
| MO-C-09 | `components/ui/*` | `Button` (états), `Input` (erreur), `Modal`, `LoadingSpinner` | P2 |

### 5.3 Contrat et intégration

| ID | Cas | Attendu | Prio |
|---|---|---|---|
| MO-CT-01 | Le payload de `useScan` est accepté par le backend réel | **échoue aujourd'hui** — format de challenge (B1) | **P0** |
| MO-CT-02 | `POST /auth/student/login` | email + identifiant unique → token de capacité `etudiant` | **P0** |
| MO-CT-03 | Capacité du token | un token `etudiant` est refusé sur `/admin/*` (403) | **P0** |
| MO-CT-04 | `GET /student/qrcode/current` | réservé au délégué ; 403 sinon | **P0** |
| MO-CT-05 | Types partagés | `ScanPayload`/`ScanResponse` alignés sur les réponses réelles du serveur | P1 |

### 5.4 E2E sur appareil (Maestro) et campagne manuelle

| ID | Parcours | Attendu | Prio |
|---|---|---|---|
| MO-E2E-01 | Connexion → scan → confirmation | présence enregistrée, heure serveur affichée | **P0** |
| MO-E2E-02 | Scan hors salle | refus avec la distance annoncée | **P0** |
| MO-E2E-03 | Scan en mode avion | message réseau, pas de perte de saisie | **P0** |
| MO-E2E-04 | Permissions refusées (caméra / GPS / Wi-Fi) | trois écrans d'explication distincts, réessai possible | **P0** |
| MO-E2E-05 | Backend Render endormi (démarrage à froid) | l'attente jusqu'à 30 s n'est pas confondue avec un échec | **P0** |
| MO-E2E-06 | Parcours du délégué | affichage du QR, partage, sa propre présence enregistrée | **P0** |
| MO-E2E-07 | Application mise en arrière-plan pendant un scan | reprise cohérente | P1 |
| MO-E2E-08 | Rotation / petit écran | mise en page tenue | P2 |

Matrice d'appareils minimale : Android 11 et 14 (un appareil bas de gamme, un récent), iOS 16+
si un build iOS est produit — le SSID n'est pas lisible sur iOS sans droit particulier, ce qui
change le résultat de `MO-U-05` et doit être écrit dans le mémoire comme une limite.

---

## 6. Outillage transverse et intégration continue

### 6.1 Chaîne CI à créer (`.github/workflows/ci.yml`)

| Étage | Contenu | Déclencheur | Seuil bloquant |
|---|---|---|---|
| 1 — Lint & types | `eslint .` (frontend), `tsc --noEmit` (mobile), `pint --test` (backend) | chaque push | toute erreur |
| 2 — Unitaire | `vitest run --coverage`, `php artisan test --coverage --min=70`, `jest` (mobile) | chaque push | couverture < 70 % |
| 3 — Contrat | `FE-CT-*`, `MO-CT-*`, `BE-CT-01` contre un backend éphémère | chaque push | tout échec |
| 4 — E2E | Playwright `frontend-local` + `api-local` sur services docker | chaque PR | tout échec P0 |
| 5 — Sécurité | `composer audit`, `npm audit`, ZAP baseline | quotidien + PR | vulnérabilité `high` |
| 6 — Charge | k6 `LOAD-01` | hebdomadaire + avant soutenance | p95 > 500 ms |
| 7 — Fumée prod | `tests/` projet `api-production` + `@smoke` frontend | après déploiement | tout échec |

Services docker requis pour l'étage 4 : Postgres 15, backend `php artisan serve`, frontend
`vite preview`, Mailpit pour capter les emails de `E2E-AUTH-11/12` et `E2E-SA-02`.

### 6.2 Données de test

| Besoin | Décision |
|---|---|
| Jeu déterministe | `TestSuiteSeeder` dédié, distinct de `DemoPresenceSeeder` (qui vise la démonstration en production) |
| Isolation | une base par exécution CI ; en local, le Postgres jetable du port 55433 |
| Événement toujours scannable | un événement ancré sur `now()` recalculé par le seeder, comme le fait déjà `DemoPresenceSeeder` |
| Deux établissements | obligatoire pour tous les cas de cloisonnement (`E2E-STU-11`, `E2E-ANO-03`, `E2E-SUP-04`, `SEC-A01-01/02`) |
| Secrets | jamais dans `tests/.env.test` versionné ; passer par les secrets du dépôt |

### 6.3 Critères de sortie

Une version n'est livrable que si :

1. Les cas **P0** de chaque plan passent — sans exception tolérée.
2. La couverture de lignes atteint 70 % côté frontend **et** backend (exigence §2.1.3).
3. `LOAD-01` tient p95 < 500 ms (hypothèse H3), mesuré et archivé.
4. Aucune vulnérabilité `high` ouverte sur `composer audit` / `npm audit`.
5. Les huit constats de la section 2 sont soit corrigés, soit consignés comme limites assumées
   dans le mémoire §4.6.
6. Aucun test conditionnel : plus de `if (await x.isVisible())` ni de `test.skip()` silencieux.

---

## 7. Matrice de traçabilité

### Hypothèses du mémoire

| Hypothèse | Cas de test qui la valident |
|---|---|
| H1 — QR dynamique + identifiant unique résistants au partage | `E2E-SCAN-02/03/08/09/10/17`, `BE-QRC-01..03`, `BE-INT-01`, `MO-E2E-01/06` |
| H2 — IA modulaire + validation humaine | `E2E-IA-01..08`, `IA-01..07`, `BE-SRV-01..05` |
| H3 — 500 scans simultanés, p95 < 500 ms | `LOAD-01..05` |
| H4 — Interaction par smartphone | `MO-E2E-01..08`, `FE-RESP-01` |

### Cahier des charges

| § CDC | Exigence | Cas |
|---|---|---|
| 7.1.1 / 7.1.2 | Inscription individuelle et CSV | `E2E-STU-01/07/08` |
| 7.1.3 | Identifiant unique déterministe | `E2E-STU-01`, `IdentifiantServiceTest` (existant) |
| 7.2.1 / 7.2.2 | Cours manuels et import IA | `E2E-CRS-01`, `E2E-IA-07` |
| 7.2.3 | Association cours↔étudiant | `BE-ENR-01..05` (pas d'écran — B5) |
| 7.3.1 / 7.3.2 | Import PDF, création d'événements | `E2E-IA-01`, `E2E-CRS-04` |
| 7.3.3 | Fenêtre de validation | `E2E-SCAN-04/05` — **à trancher, voir B4** |
| 7.4.1 | Processus de scan | `E2E-SCAN-01`, `FE-CT-01` |
| 7.4.2 | Les 7 règles de validation | `E2E-SCAN-01/02/06/05/07/08/09` |
| 8.3 | Validation humaine obligatoire | `E2E-IA-02/03` |
| 8.4 | Échecs et quotas IA | `E2E-IA-05/06`, `IA-06/07` |
| 9.1 | Auth, rate limiting, audit | `E2E-AUTH-01..14`, `SEC-A07-01`, `BE-AUD-01` |
| 9.2.1 | Régénération du QR | `E2E-SCAN-17`, `BE-QRC-01` |
| 9.2.2 | Device fingerprinting | `E2E-SCAN-09/10`, `MO-U-01/02` |
| 9.2.3 | Unicité (étudiant, événement) | `E2E-SCAN-08`, `BE-INT-01` |
| 9.4 | Données personnelles | `SEC-RGPD-01` |
| 10.1 | 500 scans simultanés | `LOAD-01` |
| 10.2 | Continuité (panne serveur, réseau, IA) | `E2E-ROB-01`, `MO-E2E-03/05`, `IA-06` |
| 12 | Multi-établissements | `E2E-STU-11`, `E2E-SA-07`, `SEC-A01-01/02` |
| 15.1 / 15.2 | Filtres et indicateurs | `E2E-REP-01..12` |

---

## 8. Ordre d'exécution proposé

| Étape | Contenu | Pourquoi d'abord |
|---|---|---|
| 1 | Trancher B1, B2, B4 ; construire l'écran de B3 | trois parcours du mémoire sont aujourd'hui intestables ou cassés |
| 2 | `O1`–`O6`, `O7`–`O10`, `O11`–`O15` | rien de mesurable sans couverture, MSW, factories et harnais mobile |
| 3 | `FE-CT-01..04`, `BE-CT-01`, `MO-CT-01..05` | les tests de contrat révèlent la classe de bugs que les suites actuelles ne peuvent pas voir |
| 4 | Tous les P0 de `E2E-SCAN-*`, `E2E-AUTH-*` | le cœur du système et son point d'entrée |
| 5 | P0 restants : `BE-REP-*`, `BE-CSV-*`, `SEC-*` | plus grosses surfaces sans aucun test |
| 6 | `LOAD-*` et `IA-*` | rendre le chapitre 4 du mémoire reproductible |
| 7 | P1 puis P2, mise en place de la CI (§6.1) | consolidation |
