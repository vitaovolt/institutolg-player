<?php

namespace App\Http\Controllers\Api;

use App\Actions\ImportarDaPastaCompartilhada;
use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Services\Integrations\ClientePastaDrive;
use App\Support\ApiResponse;
use App\Support\RelatorioImportacaoPasta;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ImportarPastaController extends Controller
{
    use ApiResponse;

    public function mostrar(): JsonResponse
    {
        $this->authorize('viewAny', Curso::class);

        return $this->ok(RelatorioImportacaoPasta::ler());
    }

    public function iniciar(ImportarDaPastaCompartilhada $importar, ClientePastaDrive $cliente): JsonResponse
    {
        $this->authorize('create', Curso::class);

        try {
            $relatorio = $importar->handle($cliente);
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        $mensagem = ! empty($relatorio['iniciou'])
            ? 'Importando da pasta compartilhada…'
            : 'Já estamos importando da pasta compartilhada.';

        unset($relatorio['iniciou']);

        return $this->ok($relatorio, $mensagem);
    }
}