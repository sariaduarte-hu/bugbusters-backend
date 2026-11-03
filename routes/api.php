<?php

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\PollController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Openbaar
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Alleen met geldige token (Authorization: Bearer ...)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::delete('/user', [AuthController::class, 'destroy'])->middleware('throttle:5,1');
    Route::put('/user/password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout']);

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
