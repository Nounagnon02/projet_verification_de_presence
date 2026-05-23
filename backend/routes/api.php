<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ImportController;
use App\Http\Controllers\Api\Admin\StudentController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\QrCodeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Routes publiques pour les étudiants (Scan)
// Rate limiting : max 3 requêtes/minute par device/IP (CDC 9.2.4)
Route::prefix('presence')->group(function () {
    Route::post('/scan', [PresenceController::class, 'scan'])
        ->middleware('throttle:scan-presence')
        ->name('api.presence.scan');
});

// Routes protégées pour l'administration
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    
    // Dashboard & Stats
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    // Étudiants
    Route::apiResource('students', StudentController::class);
    
    // QR Code management
    Route::get('/qrcode/{evenementId}/generate', [QrCodeController::class, 'generate']);
    
    // Exports
    Route::get('/reports/presence/{evenementId}/pdf', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportPdf']);
    Route::get('/reports/presence/{evenementId}/csv', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportCsv']);

    // Alerts
    Route::get('/alerts', [\App\Http\Controllers\Api\Admin\AlertController::class, 'index']);
    Route::post('/alerts/{id}/resolve', [\App\Http\Controllers\Api\Admin\AlertController::class, 'resolve']);

    // Imports (Gemini / CSV)
    Route::post('/import/students', [ImportController::class, 'students']);
    Route::post('/import/schedule', [ImportController::class, 'schedule']); // Gemini API based
});

// Auth User Info
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
