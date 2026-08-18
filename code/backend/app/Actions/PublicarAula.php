<?php

namespace App\Actions;

use App\Models\Aula;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicarAula
{
    public function handle(Aula $aula): Aula
    {
        if ($aula->publicada) {
            return $aula;
        }

        if (! $aula->estaProntaParaAssistir()) {
            throw new HttpException(422, 'A aula ainda não está pronta para publicar.');
        }

        $aula->update([
            'publicada' => true,
            'publicada_em' => now(),
        ]);

        return $aula->fresh();
    }
}
