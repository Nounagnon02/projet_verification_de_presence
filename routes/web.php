<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\AttendanceSessionController;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::get('/home', function () {
    return view('welcome');
})->name('home');

// Pages légales et informatives
Route::get('/about', function () {
    return view('about');
})->name('about');
Route::get('/privacy', function () {
    return view('legal.privacy');
})->name('privacy');
Route::get('/terms', function () {
    return view('legal.terms');
})->name('terms');
Route::get('/security', function () {
    return view('legal.security', ['securityInfo' => [
        'encryption' => 'Chiffrement en transit (HTTPS/TLS)',
        'hosting' => 'Render',
        'database' => 'PostgreSQL',
        'backup' => 'Automatique',
        'compliance' => ['HTTPS'],
        'last_audit' => date('Y-m-d')
    ]]);
})->name('security');
Route::get('/contact', function () {
    return view('contact');
})->name('contact');
Route::get('/demo', function () {
    return view('demo');
})->name('demo');
Route::get('/documentation', function () {
    return view('documentation');
})->name('documentation');
Route::get('/faq', function () {
    return view('faq');
})->name('faq');
Route::get('/features', function () {
    return view('features');
})->name('features');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [PresenceController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboardV', [PresenceController::class, 'dashboardV'])->name('dashboardV');
    Route::post('/ajout-multiple', [PresenceController::class, 'ajoutMultiple'])->name('ajout.multiple');
    Route::post('/groupes/{group}/verif', [PresenceController::class, 'verif'])->name('verif');
    Route::get('/statistiques', [PresenceController::class, 'statistiques'])->name('statistiques');
    Route::get('/statistiques-avancees', [PresenceController::class, 'statistiquesAvancees'])->name('statistiques.avancees');

    // Gestion des membres
    Route::get('/membres', [PresenceController::class, 'listeMembres'])->name('membres');
    Route::get('/membres/{id}/edit', [PresenceController::class, 'editMembre'])->name('membres.edit');
    Route::put('/membres/{id}', [PresenceController::class, 'updateMembre'])->name('membres.update');
    Route::delete('/membres/{id}', [PresenceController::class, 'deleteMembre'])->name('membres.delete');
    Route::get('/membres/{member}/carte', [PresenceController::class, 'printCard'])->name('membres.print-card');

    // Sessions de présence (scan QR par le responsable)
    Route::post('/groupes/{group}/sessions', [AttendanceSessionController::class, 'open'])->name('sessions.open');
    Route::post('/sessions/{session}/fermer', [AttendanceSessionController::class, 'close'])->name('sessions.close');
    Route::get('/sessions/{session}', [AttendanceSessionController::class, 'showScan'])->name('sessions.scan');
    Route::post('/sessions/{session}/scan', [AttendanceSessionController::class, 'scan'])->name('sessions.scan.submit');

    // RGPD
    Route::get('/rgpd', [\App\Http\Controllers\RgpdController::class, 'index'])->name('rgpd.index');
    Route::post('/rgpd/consent', [\App\Http\Controllers\RgpdController::class, 'consent'])->name('rgpd.consent');
    Route::post('/rgpd/withdraw', [\App\Http\Controllers\RgpdController::class, 'withdraw'])->name('rgpd.withdraw');

    // Heatmap
    Route::get('/heatmap', [\App\Http\Controllers\HeatmapController::class, 'index'])->name('heatmap.index');
    Route::get('/heatmap/data', [\App\Http\Controllers\HeatmapController::class, 'getData'])->name('heatmap.data');

    // Alertes & Notifications (par groupe)
    Route::get('/groupes/{group}/alerts', [\App\Http\Controllers\AlertController::class, 'index'])->name('alerts.index');
    Route::put('/groupes/{group}/alerts', [\App\Http\Controllers\AlertController::class, 'update'])->name('alerts.update');
    Route::get('/groupes/{group}/alerts/absents', [\App\Http\Controllers\AlertController::class, 'getAbsentMembers'])->name('alerts.absent-members');
});

// Route pour changer de langue
Route::get('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

// Route offline PWA
Route::view('/offline', 'offline');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
