<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\PollController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ProfileController;

/*
|--------------------------------------------------------------------------
| Authenticatie
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
});

// Registreren
Route::post('/register', [AuthController::class, 'register']);

// Inloggen
Route::post('/login', [AuthController::class, 'login']);

// Uitloggen (vereist een geldig authenticatietoken)
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');

// Gegevens van de ingelogde gebruiker ophalen
Route::get('/user', function (Request $request) {
    return response()->json($request->user());
})->middleware('auth:sanctum');


/*
|--------------------------------------------------------------------------
| Polls
|--------------------------------------------------------------------------
*/

// Polls bekijken is openbaar; aanmaken vereist authenticatie.
Route::get('/polls', [PollController::class, 'index']);
Route::post('/polls', [PollController::class, 'store'])
    ->middleware('auth:sanctum');

// Evenementen bekijken is openbaar; aanmaken vereist authenticatie.
Route::get('/events', [EventController::class, 'index']);
Route::post('/events', [EventController::class, 'store'])
    ->middleware('auth:sanctum');