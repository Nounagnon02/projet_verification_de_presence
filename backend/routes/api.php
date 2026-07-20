<?php

use App\Http\Controllers\Api\Admin\AnneeAcademiqueController;
use App\Http\Controllers\Api\Admin\ChatController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EcController;
use App\Http\Controllers\Api\Admin\EnrollmentController;
use App\Http\Controllers\Api\Admin\EvenementController;
use App\Http\Controllers\Api\Admin\FiliereController;
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

// Routes publiques pour les étudiants (Scan)
// Rate limiting : max 3 requêtes/minute par device/IP (CDC 9.2.4)
Route::prefix('presence')->group(function () {
    Route::post('/scan', [PresenceController::class, 'scan'])
        ->middleware('throttle:scan-presence')
        ->name('api.presence.scan');

    // Récupération publique des informations du cours via le token QR (CDC 7.4.1)
    Route::get('/course-by-token/{token}', [PresenceController::class, 'courseByToken'])
        ->name('api.presence.course-by-token');
});

// Routes protégées pour l'administration (faculté scope via scoped.etablissement)
Route::middleware(['auth:sanctum', 'scoped.etablissement', 'throttle:api'])->prefix('admin')->group(function () {

    // Dashboard & Stats
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/attendance-trend', [DashboardController::class, 'attendanceTrend']);
    Route::get('/dashboard/top-absences', [DashboardController::class, 'topAbsences']);
    Route::get('/dashboard/today-events', [DashboardController::class, 'todayEvents']);

    // Étudiants
    Route::apiResource('students', StudentController::class);

    // Inscriptions étudiant-cours (CDC 7.2.3)
    Route::get('/students/{student}/ecs', [EnrollmentController::class, 'index']);
    Route::get('/students/{student}/ecs-available', [EnrollmentController::class, 'available']);
    Route::post('/students/{student}/ecs', [EnrollmentController::class, 'store']);
    Route::delete('/students/{student}/ecs/{ec}', [EnrollmentController::class, 'destroy']);
    Route::post('/students/{student}/ecs/reset', [EnrollmentController::class, 'reset']);

    // Présences / Historique
    Route::get('/presence/history', [PresenceHistoryController::class, 'index']);
    Route::get('/presence/export', [PresenceHistoryController::class, 'export']);
    Route::get('/presence/stats', [PresenceHistoryController::class, 'stats']);
    Route::get('/students/{student}/stats', [PresenceHistoryController::class, 'studentStats']);

    // UE / EC / Cours
    Route::apiResource('ues', UeController::class);
    Route::apiResource('ecs', EcController::class)->except(['show']);

    // Événements
    Route::apiResource('evenements', EvenementController::class);

    // Filières
    Route::apiResource('filieres', FiliereController::class);

    // Salles (configuration géolocalisation + réseau)
    Route::get('/salles/disponibles', [SalleController::class, 'disponibles']);
    Route::apiResource('salles', SalleController::class);

    // Années académiques
    Route::apiResource('annees-academiques', AnneeAcademiqueController::class);
    Route::patch('/annees-academiques/{anneeAcademique}/activate', [AnneeAcademiqueController::class, 'activate']);

    // QR Code
    Route::get('/qrcode/{evenementId}/generate', [QrCodeController::class, 'generate']);

    // Validation manuelle des présences (Admin)
    Route::prefix('presence')->group(function () {
        Route::get('/pending', [PresenceController::class, 'pendingValidations'])->name('admin.presence.pending');
        Route::patch('/{presence}/validate', [PresenceController::class, 'validateManual'])->name('admin.presence.validate');
        Route::patch('/{presence}/reject', [PresenceController::class, 'rejectManual'])->name('admin.presence.reject');
    });

    // Exports / Rapports
    Route::get('/reports/presence/{evenementId}/pdf', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportPdf']);
    Route::get('/reports/department/{filiere}', [\App\Http\Controllers\Api\Admin\ReportController::class, 'departmentReport']);
    Route::get('/reports/semester/{anneeAcademique}', [\App\Http\Controllers\Api\Admin\ReportController::class, 'semesterReport']);
    Route::get('/reports/semester-comparison', [\App\Http\Controllers\Api\Admin\ReportController::class, 'semesterComparison']);
    Route::get('/reports/filiere-stats', [\App\Http\Controllers\Api\Admin\ReportController::class, 'filiereStats']);
    Route::get('/reports/filtered', [\App\Http\Controllers\Api\Admin\ReportController::class, 'filteredStats']);
    Route::get('/reports/excel/export', [\App\Http\Controllers\Api\Admin\ReportController::class, 'excelExport']);

    // Importations (Gemini / CSV)
    Route::post('/import/students', [ImportController::class, 'students']);
    Route::post('/import/schedule', [ImportController::class, 'schedule']);
    Route::post('/import/courses', [ImportController::class, 'courses']);
    Route::post('/import/validate-events', [ImportController::class, 'validateEvents']);
    Route::post('/import/validate-courses', [ImportController::class, 'validateCourses']);
    Route::get('/import/analysis-status/{id}', [ImportController::class, 'analysisStatus']);

    // Alertes
    Route::get('/alerts', [\App\Http\Controllers\Api\Admin\AlertController::class, 'index']);
    Route::post('/alerts/{id}/resolve', [\App\Http\Controllers\Api\Admin\AlertController::class, 'resolve']);

    // Tickets de support
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply']);
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);

    // Chat / Messagerie
    Route::get('/chat/conversations', [ChatController::class, 'conversations']);
    Route::get('/chat/conversations/{conversation}/messages', [ChatController::class, 'messages']);
    Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);
    Route::post('/chat/conversations/{conversation}/close', [ChatController::class, 'closeConversation']);

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

    // Authentification à deux facteurs (2FA)
    Route::post('/profile/2fa/enable', [ProfileController::class, 'enable2FA']);
    Route::post('/profile/2fa/confirm', [ProfileController::class, 'confirm2FA']);
    Route::post('/profile/2fa/disable', [ProfileController::class, 'disable2FA']);
    Route::post('/profile/2fa/verify', [ProfileController::class, 'verify2FA']);

    // Sessions actives
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::delete('/sessions/others', [SessionController::class, 'destroyOthers']);
});

// Routes Super Admin UAC
Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('super-admin')->group(function () {
    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index']);
    Route::apiResource('/etablissements', EtablissementController::class);
    Route::post('/etablissements/import', [BulkRegistrationController::class, 'import']);
    Route::get('/etablissements/{etablissement}/stats', [EtablissementController::class, 'stats']);
    Route::post('/etablissements/{etablissement}/resend-credentials', [EtablissementController::class, 'resendCredentials']);
});

// Auth User Info & Logout
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
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
