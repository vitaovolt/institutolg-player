<?php

use App\Http\Controllers\Api\AulaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BibliotecaController;
use App\Http\Controllers\Api\CapaAulaController;
use App\Http\Controllers\Api\CopiaDriveController;
use App\Http\Controllers\Api\CursoController;
use App\Http\Controllers\Api\DisciplinaController;
use App\Http\Controllers\Api\EnvioAulaController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PublicacaoAulaController;
use App\Http\Controllers\Api\ResumoDoMesController;
use App\Http\Controllers\Api\TurmaController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('/health', HealthController::class);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::put('/envios/{token}', [EnvioAulaController::class, 'receber'])->middleware('throttle:60,1');
    Route::post('/envios/{token}/partes', [EnvioAulaController::class, 'parte'])->middleware('throttle:upload-partes');
    Route::post('/envios/{token}/completar-multipart', [EnvioAulaController::class, 'completarPartes'])->middleware('throttle:60,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);

        Route::get('/biblioteca', BibliotecaController::class);
        Route::get('/resumo-mes', ResumoDoMesController::class);
        Route::apiResource('usuarios', UsuarioController::class)->parameters(['usuarios' => 'user']);

        Route::apiResource('cursos', CursoController::class);
        Route::apiResource('cursos.turmas', TurmaController::class)->shallow();
        Route::apiResource('turmas.disciplinas', DisciplinaController::class)->shallow();
        Route::apiResource('disciplinas.aulas', AulaController::class)->shallow();

        Route::post('/disciplinas/{disciplina}/envios', [EnvioAulaController::class, 'iniciar']);
        Route::post('/aulas/{aula}/envios/concluir', [EnvioAulaController::class, 'concluir']);
        Route::post('/aulas/{aula}/envios/reprocessar', [EnvioAulaController::class, 'reprocessar']);
        Route::post('/aulas/{aula}/envios/substituir', [EnvioAulaController::class, 'substituir']);
        Route::post('/aulas/{aula}/publicar', [PublicacaoAulaController::class, 'publicar']);
        Route::post('/aulas/{aula}/despublicar', [PublicacaoAulaController::class, 'despublicar']);
        Route::post('/aulas/{aula}/drive/sincronizar', [CopiaDriveController::class, 'sincronizar']);
        Route::post('/aulas/{aula}/drive/reprocessar', [CopiaDriveController::class, 'reprocessar']);
        Route::post('/aulas/{aula}/capa', [CapaAulaController::class, 'salvar']);
        Route::delete('/aulas/{aula}/capa', [CapaAulaController::class, 'destruir']);
    });
});
