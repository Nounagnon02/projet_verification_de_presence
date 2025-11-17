<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PresenceController;

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

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [PresenceController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboardV', [PresenceController::class, 'dashboardV'])->name('dashboardV');
    Route::post('/ajout', [PresenceController::class, 'ajout'])->name('ajout');
    Route::post('/verif', [PresenceController::class, 'verif'])->name('verif');
    Route::get('/statistiques', [PresenceController::class, 'statistiques'])->name('statistiques');
});

Route::get('/statistiques', [PresenceController::class, 'statistiques'])->name('statistiques');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
