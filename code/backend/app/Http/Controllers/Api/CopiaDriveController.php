<?php

namespace App\Http\Controllers\Api;

use App\Actions\ReprocessarCopiaDrive;
use App\Http\Controllers\Controller;
use App\Http\Resources\AulaResource;
use App\Models\Aula;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CopiaDriveController extends Controller
{
    use ApiResponse;

    public function reprocessar(Aula $aula, ReprocessarCopiaDrive $reprocessar): JsonResponse
    {
        $this->authorize('update', $aula);

        try {
            $aula = $reprocessar->handle($aula);
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        $aula->load(['disciplina.turma.curso']);

        return $this->ok(AulaResource::make($aula)->resolve(), 'Enviando a cópia de novo');
    }
}
