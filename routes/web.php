<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\QrCodeController;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::get('/home', function () {
    return view('welcome');
})->name('home');

/*Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/dashboardV', function () {
    return view('dashboardV');
})->middleware(['auth', 'verified'])->name('dashboardV');*/

Route::middleware(['auth', 'verified', 'locale'])->group(function () {
    Route::get('/dashboard', [PresenceController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboardV', [PresenceController::class, 'dashboardV'])->name('dashboardV');
    Route::post('/ajout', [PresenceController::class, 'ajout'])->name('ajout');
    Route::post('/ajout-multiple', [PresenceController::class, 'ajoutMultiple'])->name('ajout.multiple');
    Route::post('/verif', [PresenceController::class, 'verif'])->name('verif');
    Route::get('/statistiques', [PresenceController::class, 'statistiques'])->name('statistiques');
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

require __DIR__.'/auth.php';
