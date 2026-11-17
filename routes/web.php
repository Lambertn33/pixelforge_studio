<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/generations/estimate', [GenerationController::class, 'estimate'])
        ->name('generations.estimate');

    Route::get('/usage', UsageController::class)->name('usage.index');

    Route::resource('generations', GenerationController::class)
        ->only(['index', 'create', 'store', 'show']);
});
