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
use App\Http\Controllers\Api\Admin\SessionController;
use App\Http\Controllers\Api\Admin\StudentController;
use App\Http\Controllers\Api\Admin\TicketController;
use App\Http\Controllers\Api\Admin\UeController;
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

// Routes protégées pour l'administration
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {

    // Dashboard & Stats
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/heatmap', [DashboardController::class, 'heatmap']);
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
    Route::get('/presence/stats', [PresenceHistoryController::class, 'stats']);
    Route::get('/students/{student}/stats', [PresenceHistoryController::class, 'studentStats']);

    // UE / EC / Cours
    Route::apiResource('ues', UeController::class);
    Route::apiResource('ecs', EcController::class)->except(['show']);

    // Événements
    Route::apiResource('evenements', EvenementController::class);

    // Filières
    Route::apiResource('filieres', FiliereController::class);

    // Années académiques
    Route::apiResource('annees-academiques', AnneeAcademiqueController::class);
    Route::patch('/annees-academiques/{anneeAcademique}/activate', [AnneeAcademiqueController::class, 'activate']);

    // QR Code
    Route::get('/qrcode/{evenementId}/generate', [QrCodeController::class, 'generate']);

    // Exports / Rapports
    Route::get('/reports/presence/{evenementId}/pdf', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportPdf']);
    Route::get('/reports/presence/{evenementId}/csv', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportCsv']);
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

    // Sessions actives
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::delete('/sessions/others', [SessionController::class, 'destroyOthers']);
});

// Auth User Info & Logout
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
});
