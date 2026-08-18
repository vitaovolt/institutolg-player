<?php

namespace App\Http\Controllers\Api;

use App\Actions\CompletarPartesDoEnvio;
use App\Actions\ConcluirEnvioDaAula;
use App\Actions\GerarUrlDaParteDoEnvio;
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
use App\Support\ModoEnvioArquivo;
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

        return $this->ok(
            $this->payloadUpload($aula),
            $criadaAgora ? 'Envio iniciado' : 'Envio retomado',
            $criadaAgora ? 201 : 200
        );
    }

    public function receber(Request $request, string $token, ReceberArquivoDoEnvio $receber): JsonResponse
    {
        try {
            $aula = $receber->handle($token, $this->corpoDoUpload($request));
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        return $this->ok(AulaResource::make($aula)->resolve(), 'Arquivo recebido');
    }

    public function parte(Request $request, string $token, GerarUrlDaParteDoEnvio $gerar): JsonResponse
    {
        $data = $request->validate([
            'part_number' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        try {
            $url = $gerar->handle($token, (int) $data['part_number']);
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        return $this->ok(['url' => $url, 'part_number' => (int) $data['part_number']], 'Parte pronta');
    }

    public function completarPartes(Request $request, string $token, CompletarPartesDoEnvio $completar): JsonResponse
    {
        $data = $request->validate([
            'parts' => ['required', 'array', 'min:1', 'max:10000'],
            'parts.*.part_number' => ['required', 'integer', 'min:1', 'max:10000'],
            'parts.*.etag' => ['required', 'string', 'max:200'],
        ]);

        try {
            $aula = $completar->handle($token, $data['parts']);
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

        return $this->ok($this->payloadUpload($aula), 'Envio de substituição iniciado');
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadUpload(Aula $aula): array
    {
        $multipart = ModoEnvioArquivo::usaMultipart();
        $token = $aula->token_upload;

        return [
            'aula' => AulaResource::make($aula)->resolve(),
            'upload_path' => $token ? '/envios/'.$token : null,
            'upload_method' => $multipart ? 'multipart' : 'PUT',
            'part_size' => $multipart ? ModoEnvioArquivo::tamanhoParte() : null,
            'upload_max_bytes' => (int) config('biblioteca.upload_max_bytes'),
        ];
    }

    private function corpoDoUpload(Request $request): mixed
    {
        $resource = $request->getContent(true);

        if (is_resource($resource)) {
            return $resource;
        }

        return (string) $request->getContent();
    }
}
