<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\QrCodeController;
use Illuminate\Support\Facades\DB;

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
        'encryption' => 'AES-256',
        'hosting' => 'Render',
        'database' => 'Turso',
        'backup' => 'Automatique',
        'compliance' => ['RGPD', 'HTTPS'],
        'last_audit' => date('Y-m-d')
    ]]);
})->name('security');
Route::get('/demo', function () {
    return view('demo');
})->name('demo');

/*Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/dashboardV', function () {
    return view('dashboardV');
})->middleware(['auth', 'verified'])->name('dashboardV');*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [PresenceController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboardV', [PresenceController::class, 'dashboardV'])->name('dashboardV');
    Route::post('/ajout', [PresenceController::class, 'ajout'])->name('ajout');
    Route::post('/ajout-multiple', [PresenceController::class, 'ajoutMultiple'])->name('ajout.multiple');
    Route::post('/verif', [PresenceController::class, 'verif'])->name('verif');
    Route::get('/statistiques', [PresenceController::class, 'statistiques'])->name('statistiques');

// Route de debug pour vérifier la DB (à supprimer en production)
Route::get('/debug-db', function () {
    try {
        $dbPath = config('database.connections.sqlite.database');
        $exists = file_exists($dbPath);
        $size = $exists ? filesize($dbPath) : 0;

        return response()->json([
            'db_path' => $dbPath,
            'exists' => $exists,
            'size' => $size . ' bytes',
            'tables' => $exists ? \DB::select("SELECT name FROM sqlite_master WHERE type='table'") : []
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});
    Route::get('/statistiques-avancees', [PresenceController::class, 'statistiquesAvancees'])->name('statistiques.avancees');

    // Gestion des membres
    Route::get('/membres', [PresenceController::class, 'listeMembres'])->name('membres');
    Route::get('/membres/{id}/edit', [PresenceController::class, 'editMembre'])->name('membres.edit');
    Route::put('/membres/{id}', [PresenceController::class, 'updateMembre'])->name('membres.update');
    Route::delete('/membres/{id}', [PresenceController::class, 'deleteMembre'])->name('membres.delete');

    // Comparaison de périodes
    Route::get('/comparaison-periodes', [PresenceController::class, 'comparaisonPeriodes'])->name('comparaison.periodes');

    // QR Code
    Route::get('/qr/generate', [QrCodeController::class, 'generate'])->name('qr.generate');
    Route::post('/qr/generate', [QrCodeController::class, 'generate']);

    // RGPD
    Route::get('/rgpd', [\App\Http\Controllers\RgpdController::class, 'index'])->name('rgpd.index');
    Route::post('/rgpd/consent', [\App\Http\Controllers\RgpdController::class, 'consent'])->name('rgpd.consent');
    Route::post('/rgpd/withdraw', [\App\Http\Controllers\RgpdController::class, 'withdraw'])->name('rgpd.withdraw');
});

// Route pour changer de langue
Route::get('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

// Routes QR Code publiques
Route::get('/qr/{code}', [QrCodeController::class, 'scan'])->name('qr.scan');
Route::post('/qr/{code}/presence', [QrCodeController::class, 'markPresence'])->name('qr.presence');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Routes temporaires pour accéder à la DB
Route::get('/db-admin', function () {
    if (request('password') !== 'admin123') return 'Access denied';

    $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
    $html = '<h1>Database Tables</h1>';

    foreach ($tables as $table) {
        if ($table->name !== 'sqlite_sequence') {
            $count = DB::table($table->name)->count();
            $html .= "<h3>{$table->name} ({$count} records)</h3>";
            $html .= "<a href='/db-table/{$table->name}?password=admin123'>View Data</a><br><br>";
        }
    }

    return $html;
});

Route::get('/db-table/{table}', function ($table) {
    if (request('password') !== 'admin123') return 'Access denied';

    $data = DB::table($table)->limit(50)->get();
    return response()->json($data, JSON_PRETTY_PRINT);
});

require __DIR__.'/auth.php';
