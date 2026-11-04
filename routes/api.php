<?php

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\PollController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Route;

// Openbaar
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:10,1');
Route::post('/verify-email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:5,1');


// Profielfoto's (openbaar, want een <img> kan geen token meesturen)
Route::get('/photos/{file}', [ProfileController::class, 'photo'])
    ->where('file', '[A-Za-z0-9._-]+');
// Alleen met geldige token (Authorization: Bearer ...)
Route::middleware('auth:sanctum')->group(function () {
    // Mogen ook zonder bevestigd e-mailadres (om te kunnen uitloggen / de status te lezen)
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Alles hieronder vereist een bevestigd e-mailadres
    Route::middleware(EnsureEmailIsVerified::class)->group(function () {
        Route::delete('/user', [AuthController::class, 'destroy'])->middleware('throttle:5,1');
        Route::put('/user/password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1');

        Route::get('/users/{user}', [ProfileController::class, 'showUser']);
        Route::post('/events/{event}/signup', [EventController::class, 'signup']);
        Route::delete('/events/{event}/signup', [EventController::class, 'leave']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->middleware('throttle:20,1');
        Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);

        Route::get('/polls', [PollController::class, 'index']);
        Route::post('/polls', [PollController::class, 'store']);
        Route::post('/polls/{poll}/vote', [PollController::class, 'vote']);
        Route::delete('/polls/{poll}', [PollController::class, 'destroy']);

        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::delete('/events/{event}', [EventController::class, 'destroy']);
    });
});
