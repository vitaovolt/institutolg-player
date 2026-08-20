<?php

namespace App\Actions;

use App\Models\Aula;

class PublicarAulaSeNova
{
    public function __construct(private PublicarAula $publicar) {}

    public function handle(Aula $aula): Aula
    {
        $aula->refresh();

        if ($aula->publicada || ! $aula->nuncaFoiPublicada()) {
            return $aula;
        }

        if (! $aula->estaProntaParaAssistir()) {
            return $aula;
        }

        return $this->publicar->handle($aula);
    }
}
