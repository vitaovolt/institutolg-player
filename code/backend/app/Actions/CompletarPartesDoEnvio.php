<?php

namespace App\Actions;

use App\Jobs\CompletarMultipartDaAulaJob;
use App\Models\Aula;
use App\Support\ModoEnvioArquivo;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CompletarPartesDoEnvio
{
    /**
     * @param  list<array{part_number: int, etag: string}>  $partes
     */
    public function handle(string $token, array $partes): Aula
    {
        if (! ModoEnvioArquivo::usaMultipart()) {
            throw new HttpException(409, 'Este envio não usa partes. Envie o MP4 no PUT único.');
        }

        $aula = Aula::query()->where('token_upload', $token)->first();

        if ($aula === null) {
            throw new HttpException(404, 'Envio não encontrado ou já concluído.');
        }

        if (! in_array($aula->status_preparo, ['enviando', 'erro'], true)) {
            throw new HttpException(409, 'Este envio não aceita mais arquivo.');
        }

        if (blank($aula->s3_upload_id)) {
            if ((int) $aula->tamanho_bytes > 0) {
                return $aula;
            }

            throw new HttpException(422, 'O envio das partes ainda não começou.');
        }

        Cache::put('aula-multipart:'.$aula->id, $partes, now()->addDay());
        CompletarMultipartDaAulaJob::dispatch($aula->id);

        return $aula->fresh();
    }
}
