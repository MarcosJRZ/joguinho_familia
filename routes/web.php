<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;

// Tratar requisições OPTIONS para CORS (preflight requests)
Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', '*')
        ->header('Access-Control-Allow-Credentials', 'true');
})->where('any', '.*');

// Página de domínio a venda
Route::get('/', function () {
    return view('domain-sale');
})->name('domain.sale');

// Página do jogo
Route::get('/jogo', [GameController::class, 'index'])->name('game.index');

// API Routes para AJAX
Route::post('/api/generate-themes', [GameController::class, 'generateThemes'])->name('api.generate-themes');
Route::post('/api/generate-words', [GameController::class, 'generateWords'])->name('api.generate-words');
