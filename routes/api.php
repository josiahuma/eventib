<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventApiController;
use App\Http\Controllers\Api\MobileCheckInController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Events + sessions (using numeric ID not public_id)
    Route::get('/events', [EventApiController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/events/{eventId}/sessions', [EventApiController::class, 'sessions'])->middleware('throttle:60,1');

    // Mobile check-in flows
    Route::post('/check-in', [MobileCheckInController::class, 'checkIn'])->middleware('throttle:120,1');
    Route::get('/events/{eventId}/checked-in', [MobileCheckInController::class, 'checkedIn'])->middleware('throttle:60,1');
});
