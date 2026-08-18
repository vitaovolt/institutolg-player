<?php

namespace App\Http\Controllers\Api;

use App\Actions\RemoverCapaDaAula;
use App\Actions\SalvarCapaDaAula;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalvarCapaAulaRequest;
use App\Http\Resources\AulaResource;
use App\Models\Aula;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CapaAulaController extends Controller
{
    use ApiResponse;

    public function salvar(SalvarCapaAulaRequest $request, Aula $aula, SalvarCapaDaAula $salvar): JsonResponse
    {
        $arquivo = $request->file('capa');

        try {
            $aula = $salvar->handle($aula, (string) file_get_contents($arquivo->getRealPath()));
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        $aula->load(['disciplina.turma.curso']);

        return $this->ok(AulaResource::make($aula)->resolve(), 'Capa da aula salva');
    }

    public function destruir(Aula $aula, RemoverCapaDaAula $remover): JsonResponse
    {
        $this->authorize('update', $aula);

        $aula = $remover->handle($aula);
        $aula->load(['disciplina.turma.curso']);

        return $this->ok(AulaResource::make($aula)->resolve(), 'Capa removida');
    }
}
