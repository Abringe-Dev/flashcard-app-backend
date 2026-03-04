<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlashcardSetController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function() {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/flashcard-sets', [FlashcardSetController::class, 'index']);
    Route::post('/flashcard-sets', [FlashcardSetController::class, 'store']);
    Route::get('/flashcard-sets/{id}', [FlashcardSetController::class, 'show']);
    Route::delete('/flashcard-sets/{id}', [FlashcardSetController::class, 'destroy']);

    Route::post('/flashcard-sets/{id}/regenerate', [FlashcardSetController::class, 'regenerate']);
});