<?php

use App\Http\Controllers\Api\Admin\AnneeAcademiqueController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EcController;
use App\Http\Controllers\Api\Admin\EnrollmentController;
use App\Http\Controllers\Api\Admin\EvenementController;
use App\Http\Controllers\Api\Admin\FiliereController;
use App\Http\Controllers\Api\Admin\CsvImportController;
use App\Http\Controllers\Api\Admin\ImportController;
use App\Http\Controllers\Api\Admin\NotificationController;
use App\Http\Controllers\Api\Admin\PresenceHistoryController;
use App\Http\Controllers\Api\Admin\ProfileController;
use App\Http\Controllers\Api\Admin\SalleController;
use App\Http\Controllers\Api\Admin\SessionController;
use App\Http\Controllers\Api\Admin\StudentController;
use App\Http\Controllers\Api\Admin\TicketController;
use App\Http\Controllers\Api\Admin\UeController;
use App\Http\Controllers\Api\ApiDocumentationController;
use App\Http\Controllers\Api\LandingPageController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\StudentAuthController;
use App\Http\Controllers\Api\StudentQrCodeController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Api\SuperAdmin\BulkRegistrationController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\Api\SuperAdmin\EtablissementController;

// Route publique pour les statistiques de la landing page
Route::get('/landing/stats', [LandingPageController::class, 'stats']);

// Health check — monitoring de disponibilité (CDC 12)
Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected';
    }

    return response()->json([
        'success' => true,
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => $dbStatus,
            'app' => 'running',
            'version' => '1.0.0',
        ],
    ]);
});

// ============================================================
// Authentification étudiant (app mobile)
// ============================================================
Route::post('/auth/student/login', [StudentAuthController::class, 'login'])
    ->middleware('throttle:student-login');
Route::get('/auth/student/me', [StudentAuthController::class, 'me'])
    ->middleware(['auth:sanctum', 'ability:etudiant', 'throttle:api']);
Route::post('/auth/student/logout', [StudentAuthController::class, 'logout'])
    ->middleware(['auth:sanctum', 'ability:etudiant', 'throttle:api']);

// Consultation du QR Code par l'étudiant responsable (lecture seule).
Route::get('/student/qrcode/current', [StudentQrCodeController::class, 'current'])
    ->middleware(['auth:sanctum', 'ability:etudiant', 'throttle:api']);

// Route de login nommée — nécessaire pour les redirections de Sanctum
// Rate limiting : 5 tentatives/min/IP (CDC 9.1)
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:login')
    ->name('login');

// Route de mot de passe oublié — envoi d'email avec lien de réinitialisation
Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'store'])
    ->middleware('throttle:6,1');

// Route de réinitialisation du mot de passe
Route::post('/reset-password', [\App\Http\Controllers\Auth\NewPasswordController::class, 'store'])
    ->middleware('throttle:6,1');

// Récupération des informations du cours via le token QR (CDC 7.4.1) — publique :
// c'est ce qui permet à l'écran de scan d'afficher le cours avant que
// l'étudiant se connecte.
Route::prefix('presence')->group(function () {
    Route::get('/course-by-token/{token}', [PresenceController::class, 'courseByToken'])
        ->name('api.presence.course-by-token');
});

// Le scan, lui, exige désormais l'authentification étudiante : il n'est plus un
// point d'entrée public. Le corps de la requête portait auparavant
// « identifiant_unique », une chaîne déterministe reconstituable par n'importe
// quel camarade de promotion (NOM_PRENOM_MATRICULE_FILIERE_ANNEE) ; c'est le
// jeton, capacité « etudiant », qui désigne désormais l'étudiant.
// Rate limiting : par étudiant ET par IP (CDC 9.2.4) — voir AppServiceProvider.
Route::post('/presence/scan', [PresenceController::class, 'scan'])
    ->middleware(['auth:sanctum', 'ability:etudiant', 'throttle:scan-presence'])
    ->name('api.presence.scan');

// Routes protégées pour l'administration (faculté scope via scoped.etablissement)
//
// « ability:admin » écarte les jetons d'étudiant, qui portent la capacité
// « etudiant ». Les jetons d'administrateur sont créés sans capacité, donc avec
// « * », qui satisfait ce contrôle : les sessions en cours restent valides.
Route::middleware(['auth:sanctum', 'ability:admin', 'scoped.etablissement', 'password.changed', 'throttle:api', 'cloisonnement.modeles'])->prefix('admin')->group(function () {

    // Dashboard & Stats
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/attendance-trend', [DashboardController::class, 'attendanceTrend']);
    Route::get('/dashboard/top-absences', [DashboardController::class, 'topAbsences']);
    Route::get('/dashboard/today-events', [DashboardController::class, 'todayEvents']);

    // Étudiants
    // Déclarée avant apiResource pour que /students/promote ne soit pas
    // interprété comme /students/{student}.
    Route::post('/students/promote', [StudentController::class, 'promote']);
    Route::apiResource('students', StudentController::class);

    // Renvoi des identifiants de connexion : le code d'accès est tiré à
    // nouveau (jamais relu, la base n'en garde que le hachage) et l'ancien
    // devient inexploitable.
    Route::post('/students/{student}/identifiants', [StudentController::class, 'renvoyerIdentifiants']);

    // Inscriptions étudiant-cours (CDC 7.2.3)
    Route::get('/students/{student}/ecs', [EnrollmentController::class, 'index']);
    Route::get('/students/{student}/ecs-available', [EnrollmentController::class, 'available']);
    Route::post('/students/{student}/ecs', [EnrollmentController::class, 'store']);
    Route::delete('/students/{student}/ecs/{ec}', [EnrollmentController::class, 'destroy']);
    Route::post('/students/{student}/ecs/reset', [EnrollmentController::class, 'reset']);

    // Groupes de TD et de TP par promotion.
    Route::get('/groupes', [\App\Http\Controllers\Api\Admin\GroupeController::class, 'index']);
    Route::post('/groupes', [\App\Http\Controllers\Api\Admin\GroupeController::class, 'store']);
    Route::post('/groupes/repartir', [\App\Http\Controllers\Api\Admin\GroupeController::class, 'repartir']);
    Route::delete('/groupes/{groupe}', [\App\Http\Controllers\Api\Admin\GroupeController::class, 'destroy']);
    Route::put('/students/{student}/groupes', [\App\Http\Controllers\Api\Admin\GroupeController::class, 'affecter']);

    // Présences / Historique
    Route::get('/presence/history', [PresenceHistoryController::class, 'index']);
    Route::get('/presence/export', [PresenceHistoryController::class, 'export']);
    Route::get('/presence/stats', [PresenceHistoryController::class, 'stats']);
    Route::get('/students/{student}/stats', [PresenceHistoryController::class, 'studentStats']);

    // UE / EC / Cours
    Route::apiResource('ues', UeController::class);
    Route::apiResource('ecs', EcController::class)->except(['show']);

    // Événements
    // Déclarée avant apiResource pour ne pas être interprétée comme
    // /evenements/{evenement}.
    Route::get('/evenements/creneaux-emploi-du-temps', [EvenementController::class, 'creneauxEmploiDuTemps']);
    Route::apiResource('evenements', EvenementController::class);

    // Filières
    Route::post('/filieres/reconduire', [FiliereController::class, 'reconduire']);
    // Niveaux officiels (L1…M2 et leurs semestres) et programmes de l'établissement.
    Route::get('/niveaux', [FiliereController::class, 'niveaux']);
    Route::get('/programmes', [\App\Http\Controllers\Api\Admin\ProgrammeController::class, 'index']);
    Route::put('/programmes/{programme}', [\App\Http\Controllers\Api\Admin\ProgrammeController::class, 'update']);
    Route::apiResource('filieres', FiliereController::class);

    // Salles (configuration géolocalisation + réseau)
    Route::get('/salles/disponibles', [SalleController::class, 'disponibles']);
    // Import : reconnaître les salles qu'un document nomme, créer celles qui manquent.
    Route::post('/salles/reconnaitre', [SalleController::class, 'reconnaitre']);
    Route::post('/salles/depuis-nom', [SalleController::class, 'depuisNom']);
    Route::apiResource('salles', SalleController::class);

    // Années académiques
    // Le paramètre est nommé explicitement « anneeAcademique » : sans cela,
    // apiResource génère « annees_academique », qui ne correspond pas au
    // paramètre $anneeAcademique des méthodes du contrôleur — le model
    // binding échouait alors et injectait un modèle vide (show/update/destroy
    // opéraient dans le vide).
    // Consultation et bascule seulement : les années sont communes à
    // l'université et gérées par le super administrateur (/super-admin).
    Route::apiResource('annees-academiques', AnneeAcademiqueController::class)
        ->only(['index', 'show'])
        ->parameters(['annees-academiques' => 'anneeAcademique']);
    Route::patch('/annees-academiques/{anneeAcademique}/activate', [AnneeAcademiqueController::class, 'activate']);
    // Préparer l'année suivante : filières, maquette UE/EC, emploi du temps.
    Route::get('/annees-academiques/{anneeAcademique}/preparation', [AnneeAcademiqueController::class, 'preparation']);
    Route::post('/annees-academiques/{anneeAcademique}/preparer', [AnneeAcademiqueController::class, 'preparer']);

    // Calendrier : périodes des semestres et fermetures de l'établissement.
    Route::get('/calendrier', [\App\Http\Controllers\Api\Admin\CalendrierController::class, 'index']);
    Route::put('/calendrier/periodes', [\App\Http\Controllers\Api\Admin\CalendrierController::class, 'enregistrerPeriode']);
    Route::delete('/calendrier/periodes/{periode}', [\App\Http\Controllers\Api\Admin\CalendrierController::class, 'supprimerPeriode']);
    Route::post('/calendrier/fermetures', [\App\Http\Controllers\Api\Admin\CalendrierController::class, 'ajouterFermeture']);
    Route::delete('/calendrier/fermetures/{fermeture}', [\App\Http\Controllers\Api\Admin\CalendrierController::class, 'supprimerFermeture']);

    // Emploi du temps hebdomadaire : créneaux et conflits.
    Route::get('/emploi-du-temps', [\App\Http\Controllers\Api\Admin\EmploiDuTempsController::class, 'index']);
    Route::get('/emploi-du-temps/conflits', [\App\Http\Controllers\Api\Admin\EmploiDuTempsController::class, 'conflits']);
    Route::post('/emploi-du-temps', [\App\Http\Controllers\Api\Admin\EmploiDuTempsController::class, 'store']);
    Route::put('/emploi-du-temps/{creneau}', [\App\Http\Controllers\Api\Admin\EmploiDuTempsController::class, 'update']);
    Route::delete('/emploi-du-temps/{creneau}', [\App\Http\Controllers\Api\Admin\EmploiDuTempsController::class, 'destroy']);

    // QR Code
    Route::get('/qrcode/{evenementId}/generate', [QrCodeController::class, 'generate']);

    // Validation manuelle des présences (Admin)
    Route::prefix('presence')->group(function () {
        Route::get('/pending', [PresenceController::class, 'pendingValidations'])->name('admin.presence.pending');
        Route::patch('/{presence}/validate', [PresenceController::class, 'validateManual'])->name('admin.presence.validate');
        // Saisie par l'administration d'un étudiant qui n'a pas pu scanner.
        Route::get('/manuelle/{evenement}/etudiants', [\App\Http\Controllers\Api\Admin\SaisieManuelleController::class, 'etudiants'])->name('admin.presence.manuelle.etudiants');
        Route::post('/manuelle', [\App\Http\Controllers\Api\Admin\SaisieManuelleController::class, 'enregistrer'])->name('admin.presence.manuelle');
    });

    // Exports / Rapports
    Route::get('/reports/presence/{evenementId}/pdf', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportPdf']);
    Route::get('/reports/department/{filiere}', [\App\Http\Controllers\Api\Admin\ReportController::class, 'departmentReport']);
    Route::get('/reports/semester/{anneeAcademique}', [\App\Http\Controllers\Api\Admin\ReportController::class, 'semesterReport']);
    Route::get('/reports/semester-comparison', [\App\Http\Controllers\Api\Admin\ReportController::class, 'semesterComparison']);
    Route::get('/reports/filiere-stats', [\App\Http\Controllers\Api\Admin\ReportController::class, 'filiereStats']);
    Route::get('/reports/filtered', [\App\Http\Controllers\Api\Admin\ReportController::class, 'filteredStats']);
    Route::get('/reports/excel/export', [\App\Http\Controllers\Api\Admin\ReportController::class, 'excelExport']);
    Route::get('/reports/etudiants-absents', [\App\Http\Controllers\Api\Admin\ReportController::class, 'etudiantsAbsents']);
    Route::get('/reports/annee-stats', [\App\Http\Controllers\Api\Admin\ReportController::class, 'anneeStats']);

    // Importations (Gemini / CSV)
    Route::post('/import/students', [ImportController::class, 'students']);
    Route::post('/import/schedule', [ImportController::class, 'schedule']);
    Route::post('/import/courses', [ImportController::class, 'courses']);
    Route::post('/import/validate-events', [ImportController::class, 'validateEvents']);

    // Import d'emploi du temps en deux temps : vérification sans effet de bord,
    // puis persistance transactionnelle qui REVALIDE tout. L'ancien chemin
    // validate-events validait et écrivait dans le même appel, sans transaction
    // et sans contrôle de cohérence académique.
    Route::post('/import/schedule/verifier', [\App\Http\Controllers\Api\Admin\ScheduleImportController::class, 'verifier']);
    Route::post('/import/schedule/confirmer', [\App\Http\Controllers\Api\Admin\ScheduleImportController::class, 'confirmer']);
    Route::post('/import/validate-courses', [ImportController::class, 'validateCourses']);
    Route::get('/import/analysis-status/{id}', [ImportController::class, 'analysisStatus']);

    // Import CSV (UE/EC et Emploi du temps)
    Route::prefix('import/csv')->group(function () {
        Route::post('/courses', [CsvImportController::class, 'importCourses']);
        Route::post('/schedule', [CsvImportController::class, 'importSchedule']);
        Route::get('/template/{type}', [CsvImportController::class, 'downloadTemplate']);
    });

    // Alertes
    // Scans refusés, en lecture seule : les décisions se prennent dans la file
    // de validation (/presence/pending).
    Route::get('/alerts', [\App\Http\Controllers\Api\Admin\AlertController::class, 'index']);
    // Rattrapage d'un étudiant refusé à tort, depuis le refus lui-même.
    Route::post('/alerts/{id}/presence', [\App\Http\Controllers\Api\Admin\AlertController::class, 'enregistrerPresence']);

    // Tickets de support
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply']);
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    // Profil utilisateur
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // Authentification à deux facteurs (2FA). confirm/verify sous
    // « throttle:totp » : le seul throttle:api general (60/min) laissait un
    // espace de 10^6 codes largement testable dans cette marge.
    Route::post('/profile/2fa/enable', [ProfileController::class, 'enable2FA']);
    Route::post('/profile/2fa/confirm', [ProfileController::class, 'confirm2FA'])->middleware('throttle:totp');
    Route::post('/profile/2fa/disable', [ProfileController::class, 'disable2FA']);
    Route::post('/profile/2fa/verify', [ProfileController::class, 'verify2FA'])->middleware('throttle:totp');

    // Sessions actives
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::delete('/sessions/others', [SessionController::class, 'destroyOthers']);
});

// Routes Super Admin UAC
// Le groupe le plus privilegie n'imposait ni le rate limiting general, ni le
// changement du mot de passe temporaire, ni la double authentification — trois
// protections que le groupe « admin » porte pourtant toutes. Un mot de passe
// compromis y donnait un acces total et illimite.
Route::middleware(['auth:sanctum', 'role:super_admin', 'password.changed', 'throttle:api', '2fa.super_admin'])->prefix('super-admin')->group(function () {
    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index']);
    Route::apiResource('/etablissements', EtablissementController::class);
    Route::post('/etablissements/import', [BulkRegistrationController::class, 'import']);
    Route::get('/etablissements/{etablissement}/stats', [EtablissementController::class, 'stats']);

    // Années académiques de l'université.
    Route::get('/annees-academiques', [\App\Http\Controllers\Api\SuperAdmin\AnneeUniversitaireController::class, 'index']);
    Route::post('/annees-academiques', [\App\Http\Controllers\Api\SuperAdmin\AnneeUniversitaireController::class, 'store']);
    Route::put('/annees-academiques/{annee}', [\App\Http\Controllers\Api\SuperAdmin\AnneeUniversitaireController::class, 'update']);
    Route::delete('/annees-academiques/{annee}', [\App\Http\Controllers\Api\SuperAdmin\AnneeUniversitaireController::class, 'destroy']);
    Route::patch('/annees-academiques/{annee}/en-cours', [\App\Http\Controllers\Api\SuperAdmin\AnneeUniversitaireController::class, 'enCours']);

    // Jours fériés de l'université : aucune séance n'est générée ces jours-là.
    Route::get('/jours-feries', [\App\Http\Controllers\Api\SuperAdmin\JourFerieController::class, 'index']);
    Route::post('/jours-feries', [\App\Http\Controllers\Api\SuperAdmin\JourFerieController::class, 'store']);
    Route::delete('/jours-feries/{fermeture}', [\App\Http\Controllers\Api\SuperAdmin\JourFerieController::class, 'destroy']);
    Route::post('/etablissements/{etablissement}/resend-credentials', [EtablissementController::class, 'resendCredentials']);
});

// Auth User Info & Logout
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();

        // L'etablissement de rattachement est une donnee propre de l'utilisateur.
        // Sans lui, les ecrans d'administration devaient aller le chercher dans la
        // liste globale des etablissements — une route reservee au super admin, donc
        // un 404 silencieux pour tout admin de faculte.
        $user->loadMissing('etablissement');
        $data = $user->toArray();

        // Enrichir avec les données étudiant si disponibles
        $etudiant = \App\Models\Etudiant::where('email', $user->email)->first();
        if ($etudiant) {
            $data['identifiant_unique'] = $etudiant->identifiant_unique;
            $data['matricule']   = $etudiant->matricule;
            $data['nom']         = $etudiant->nom;
            $data['prenom']      = $etudiant->prenom;
        }

        return response()->json($data);
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // Étudiant : historique & statistiques personnels
    Route::get('/presence/my-history', [PresenceController::class, 'myHistory']);
    Route::get('/presence/my-stats', [PresenceController::class, 'myStats']);
});

// ========================================================================
// API DOCUMENTATION (OpenAPI/Swagger)
// ========================================================================
Route::get('/docs', [ApiDocumentationController::class, 'index'])->name('api.docs');
Route::get('/docs/json', [ApiDocumentationController::class, 'json'])->name('api.docs.json');
Route::get('/docs/yaml', [ApiDocumentationController::class, 'yaml'])->name('api.docs.yaml');
