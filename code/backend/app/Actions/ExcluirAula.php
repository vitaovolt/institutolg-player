<?php

namespace App\Actions;

use App\Jobs\LimparObjetosDaAulaJob;
use App\Models\Aula;
use Illuminate\Support\Facades\Cache;

class ExcluirAula
{
    public function handle(Aula $aula): void
    {
        Cache::lock('aula-envio:'.$aula->id, 20)->block(10, function () use ($aula): void {
            $aula->refresh();
            $snapshot = [
                's3_upload_id' => $aula->s3_upload_id,
                'chave_arquivo' => $aula->chave_arquivo,
                'chave_play' => $aula->chave_play,
                'chave_capa' => $aula->chave_capa,
            ];
            $aula->delete();
            LimparObjetosDaAulaJob::dispatch($snapshot);
        });
    }
}
