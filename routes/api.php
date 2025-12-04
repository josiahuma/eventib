<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventApiController;
use App\Http\Controllers\Api\MobileCheckInController;
use App\Http\Controllers\VoiceCheckController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('/login/google', [AuthController::class, 'loginWithGoogle'])
    ->middleware('throttle:20,1');


Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // 🔹 NEW: allow updating profile
    Route::match(['post', 'put', 'patch'], '/me', [AuthController::class, 'updateMe']);

    // ✅ Note how we use {event:public_id}
    Route::get('/events', [EventApiController::class, 'index']);
    Route::get('/events/{event:public_id}', [EventApiController::class, 'show']);
    Route::get('/events/{event:public_id}/sessions', [EventApiController::class, 'sessions']);

    // ✅ Check-in routes remain
    Route::post('/check-in', [MobileCheckInController::class, 'checkIn']);
    Route::get('/events/{event:public_id}/checked-in', [MobileCheckInController::class, 'checkedIn']);
});

Route::post('/voice-check', [VoiceCheckController::class, 'check'])
    ->name('api.voice.check');
