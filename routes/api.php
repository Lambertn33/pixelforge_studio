<?php

use App\Http\Controllers\Webhooks\PixelforgeController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/pixelforge', PixelforgeController::class)
    ->name('webhooks.pixelforge');
