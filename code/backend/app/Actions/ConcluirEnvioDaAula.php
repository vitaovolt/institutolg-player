<?php

namespace App\Actions;

use App\Jobs\PrepararVersaoDaAulaJob;
use App\Models\Aula;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConcluirEnvioDaAula
{
    public function handle(Aula $aula): Aula
    {
        return Cache::lock('aula-envio:'.$aula->id, 20)->block(10, function () use ($aula): Aula {
            $aula->refresh();

            if (in_array($aula->status_preparo, ['preparando', 'pronta'], true)) {
                return $aula;
            }

            $disk = (string) config('biblioteca.disk_aulas');

            if (! $aula->chave_arquivo || ! Storage::disk($disk)->exists($aula->chave_arquivo)) {
                throw new HttpException(422, 'O arquivo ainda não chegou. Envie o MP4 de novo.');
            }

            $aula->update([
                'status_preparo' => 'preparando',
                'enviado_em' => $aula->enviado_em ?? now(),
                'mensagem_erro' => null,
                'token_upload' => null,
            ]);

            PrepararVersaoDaAulaJob::dispatch($aula->id);

            return $aula->fresh();
        });
    }
}
