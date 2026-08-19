<?php

namespace App\Actions;

use App\Jobs\CopiarAulaParaDriveJob;
use App\Models\Aula;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SincronizarAulaComDrive
{
    public function handle(Aula $aula): Aula
    {
        if (! $aula->estaProntaParaAssistir()) {
            throw new HttpException(422, 'A aula ainda não está pronta. Sincronize depois que der para assistir.');
        }

        $enfileirar = false;

        Cache::lock('aula-drive:'.$aula->id, 20)->block(5, function () use ($aula, &$enfileirar): void {
            $aula->refresh();

            if ($aula->status_drive === 'enviando') {
                return;
            }

            $aula->update([
                'status_drive' => 'enviando',
                'mensagem_erro' => $aula->status_preparo === 'pronta' ? null : $aula->mensagem_erro,
            ]);
            $enfileirar = true;
        });

        if ($enfileirar) {
            CopiarAulaParaDriveJob::dispatch($aula->id);
        }

        return $aula->fresh();
    }
}