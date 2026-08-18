<?php

use App\Http\Controllers\PlayerPublicoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/assistir/{aula:token_publico}', [PlayerPublicoController::class, 'pagina'])
    ->name('player.pagina');
Route::get('/assistir/{aula:token_publico}/midia', [PlayerPublicoController::class, 'midia'])
    ->middleware('signed:relative')
    ->name('player.midia');
Route::get('/eduq/{aula:token_publico}', [PlayerPublicoController::class, 'eduq'])
    ->name('player.eduq');
Route::get('/capa/{aula:token_publico}', [PlayerPublicoController::class, 'capa'])
    ->name('player.capa');
