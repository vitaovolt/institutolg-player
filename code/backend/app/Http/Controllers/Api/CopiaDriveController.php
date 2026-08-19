<?php

namespace App\Http\Controllers\Api;

use App\Actions\SincronizarAulaComDrive;
use App\Http\Controllers\Controller;
use App\Http\Resources\AulaResource;
use App\Models\Aula;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CopiaDriveController extends Controller
{
    use ApiResponse;

    public function sincronizar(Aula $aula, SincronizarAulaComDrive $sincronizar): JsonResponse
    {
        $this->authorize('update', $aula);

        try {
            $aula = $sincronizar->handle($aula);
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        $aula->load(['disciplina.turma.curso']);

        return $this->ok(AulaResource::make($aula)->resolve(), 'Enviando a cópia para a pasta compartilhada.');
    }

    public function reprocessar(Aula $aula, SincronizarAulaComDrive $sincronizar): JsonResponse
    {
        return $this->sincronizar($aula, $sincronizar);
    }
}
