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
    Route::get('/events/{event:public_id}/sessions', [EventApiController::class, 'sessions']);
    Route::get('/events/{event:public_id}', [EventApiController::class, 'show']);
    Route::get('/events/{event:public_id}/attendees', [EventApiController::class, 'attendees'])->middleware('throttle:60,1');
    Route::get('/events/{event:public_id}/attendees/search', [EventApiController::class, 'searchAttendees'])->middleware('throttle:60,1');
    Route::get('/events/{event:public_id}/sessions/{session}', [EventApiController::class, 'sessionDetails']);
    Route::get('/events/{event:public_id}/sessions/{session}/attendees', [EventApiController::class, 'sessionAttendees'])->middleware('throttle:60,1');
    Route::get('/events/{event:public_id}/sessions/{session}/attendees/search', [EventApiController::class, 'searchSessionAttendees'])->middleware('throttle:60,1');
    
    // Mobile check-in flows
    Route::post('/check-in', [MobileCheckInController::class, 'checkIn'])->middleware('throttle:120,1');
    Route::get('/events/{eventId}/checked-in', [MobileCheckInController::class, 'checkedIn'])->middleware('throttle:60,1');
});
