<?php

namespace App\Actions;

use App\Jobs\PrepararVersaoDaAulaJob;
use App\Models\Aula;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReprocessarEnvioDaAula
{
    public function handle(Aula $aula): Aula
    {
        if ($aula->status_preparo === 'pronta') {
            return $aula;
        }

        $disk = (string) config('biblioteca.disk_aulas');

        if ($aula->status_preparo !== 'erro' || ! $aula->chave_arquivo || ! Storage::disk($disk)->exists($aula->chave_arquivo)) {
            throw new HttpException(422, 'Não dá para tentar de novo agora. Envie o export MP4 de novo.');
        }

        $aula->update([
            'status_preparo' => 'preparando',
            'mensagem_erro' => null,
        ]);

        PrepararVersaoDaAulaJob::dispatch($aula->id);

        return $aula->fresh();
    }
}
