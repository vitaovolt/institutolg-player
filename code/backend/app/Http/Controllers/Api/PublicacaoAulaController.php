<?php

namespace App\Http\Controllers\Api;

use App\Actions\DespublicarAula;
use App\Actions\PublicarAula;
use App\Http\Controllers\Controller;
use App\Http\Resources\AulaResource;
use App\Models\Aula;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicacaoAulaController extends Controller
{
    use ApiResponse;

    public function publicar(Aula $aula, PublicarAula $publicar): JsonResponse
    {
        $this->authorize('update', $aula);

        try {
            $aula = $publicar->handle($aula);
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        return $this->ok($this->payload($aula), 'Aula publicada');
    }

    public function despublicar(Aula $aula, DespublicarAula $despublicar): JsonResponse
    {
        $this->authorize('update', $aula);

        $aula = $despublicar->handle($aula);

        return $this->ok($this->payload($aula), 'Aula despublicada');
    }

    private function payload(Aula $aula): array
    {
        $aula->load(['disciplina.turma.curso']);

        return AulaResource::make($aula)->resolve();
    }
}
