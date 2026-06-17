<?php

use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/api/organizations', [OrganizationController::class, 'index']);
    Route::post('/api/organization/import', [OrganizationController::class, 'import']);
    Route::get('/api/organizations/{organization}/reviews', [OrganizationController::class, 'getReviews']);
    Route::post('/api/organizations/{organization}/parse-chunk', [OrganizationController::class, 'parseChunk']);
});
