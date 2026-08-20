<?php

namespace App\Actions;

use App\Jobs\VarreduraDaPastaCompartilhadaJob;
use App\Services\Integrations\ClientePastaDrive;
use App\Support\RelatorioImportacaoPasta;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ImportarDaPastaCompartilhada
{
    public function handle(ClientePastaDrive $cliente): array
    {
        if (! $cliente->podeListarPasta()) {
            throw new HttpException(422, 'A pasta compartilhada ainda não está configurada para importar.');
        }

        $enfileirar = false;
        $relatorio = RelatorioImportacaoPasta::ler();

        Cache::lock(RelatorioImportacaoPasta::LOCK, 20)->block(5, function () use (&$enfileirar, &$relatorio): void {
            $relatorio = RelatorioImportacaoPasta::ler();
            if (($relatorio['status'] ?? '') === 'importando') {
                return;
            }

            $relatorio = RelatorioImportacaoPasta::iniciar();
            $enfileirar = true;
        });

        if ($enfileirar) {
            VarreduraDaPastaCompartilhadaJob::dispatch();
            $relatorio = RelatorioImportacaoPasta::ler();
        }

        $relatorio['iniciou'] = $enfileirar;

        return $relatorio;
    }
}