<?php

namespace App\Http\Controllers\Api;

use App\Actions\ConcluirEnvioDaAula;
use App\Actions\IniciarEnvioDaAula;
use App\Actions\IniciarSubstituicaoDaAula;
use App\Actions\ReceberArquivoDoEnvio;
use App\Actions\ReprocessarEnvioDaAula;
use App\Http\Controllers\Controller;
use App\Http\Requests\IniciarEnvioRequest;
use App\Http\Resources\AulaResource;
use App\Models\Aula;
use App\Models\Disciplina;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnvioAulaController extends Controller
{
    use ApiResponse;

    public function iniciar(IniciarEnvioRequest $request, Disciplina $disciplina, IniciarEnvioDaAula $iniciar): JsonResponse
    {
        try {
            $aula = $iniciar->handle(
                $disciplina,
                $request->validated('titulo'),
                $request->validated('chave_idempotencia'),
            );
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        $criadaAgora = $aula->wasRecentlyCreated;
        $uploadPath = $aula->token_upload ? '/envios/'.$aula->token_upload : null;

        return $this->ok([
            'aula' => AulaResource::make($aula)->resolve(),
            'upload_path' => $uploadPath,
            'upload_method' => 'PUT',
        ], $criadaAgora ? 'Envio iniciado' : 'Envio retomado', $criadaAgora ? 201 : 200);
    }

    public function receber(Request $request, string $token, ReceberArquivoDoEnvio $receber): JsonResponse
    {
        try {
            $aula = $receber->handle($token, $request->getContent() ?: '');
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        return $this->ok(AulaResource::make($aula)->resolve(), 'Arquivo recebido');
    }

    public function concluir(Aula $aula, ConcluirEnvioDaAula $concluir): JsonResponse
    {
        $this->authorize('update', $aula);

        try {
            $aula = $concluir->handle($aula);
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        return $this->ok(AulaResource::make($aula)->resolve(), 'Preparando aula');
    }

    public function reprocessar(Aula $aula, ReprocessarEnvioDaAula $reprocessar): JsonResponse
    {
        $this->authorize('update', $aula);

        try {
            $aula = $reprocessar->handle($aula);
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        return $this->ok(AulaResource::make($aula)->resolve(), 'Tentando de novo');
    }

    public function substituir(Aula $aula, IniciarSubstituicaoDaAula $iniciar): JsonResponse
    {
        $this->authorize('update', $aula);

        try {
            $aula = $iniciar->handle($aula);
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        $uploadPath = $aula->token_upload ? 'envios/'.$aula->token_upload : null;

        return $this->ok([
            'aula' => AulaResource::make($aula)->resolve(),
            'upload_path' => $uploadPath,
            'upload_method' => 'PUT',
        ], 'Envio de substituição iniciado');
    }
}
