<?php

namespace App\Actions;

use App\Jobs\CopiarAulaParaDriveJob;
use App\Models\Aula;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReprocessarCopiaDrive
{
    public function handle(Aula $aula): Aula
    {
        if (! $aula->estaProntaParaAssistir()) {
            throw new HttpException(422, 'A aula ainda não está pronta. A cópia só sai depois que dá para assistir.');
        }

        if ($aula->status_drive === 'ok') {
            return $aula;
        }

        $aula->update([
            'status_drive' => 'pendente',
            'mensagem_erro' => $aula->status_preparo === 'pronta' ? null : $aula->mensagem_erro,
        ]);

        CopiarAulaParaDriveJob::dispatch($aula->id);

        return $aula->fresh();
    }
}
