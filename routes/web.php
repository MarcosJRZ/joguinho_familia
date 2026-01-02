<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;

// Página principal do jogo
Route::get('/', [GameController::class, 'index'])->name('game.index');

// API Routes para AJAX
Route::post('/api/generate-themes', [GameController::class, 'generateThemes'])->name('api.generate-themes');
Route::post('/api/generate-words', [GameController::class, 'generateWords'])->name('api.generate-words');
