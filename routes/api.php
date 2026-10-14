<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\PollController;
use App\Http\Controllers\Api\EventController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/polls', [PollController::class, 'index']);
Route::post('/polls', [PollController::class, 'store']);
Route::get('/events', [EventController::class, 'index']);
Route::post('/events', [EventController::class, 'store']);
