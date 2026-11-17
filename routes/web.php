<?php

use App\Http\Controllers\GenerationController;
use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::post('/generations/estimate', [GenerationController::class, 'estimate'])
        ->name('generations.estimate');

    Route::get('/usage', UsageController::class)->name('usage.index');

    Route::resource('generations', GenerationController::class)
        ->only(['index', 'create', 'store', 'show']);
});
