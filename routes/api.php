<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventApiController;
use App\Http\Controllers\Api\MobileCheckInController;

// --- Authentication for the mobile app ---
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

// Everything below requires a valid token
Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',     [AuthController::class, 'me']);

    // Organizer's events + sessions (for picker in app)
    Route::get('/events',                    [EventApiController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/events/{event}/sessions',   [EventApiController::class, 'sessions'])->middleware('throttle:60,1');

    // Mobile ticket/registration check-in
    Route::post('/check-in', [MobileCheckInController::class, 'checkIn'])->middleware('throttle:120,1');
});
