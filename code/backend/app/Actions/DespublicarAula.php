<?php

namespace App\Actions;

use App\Models\Aula;

class DespublicarAula
{
    public function handle(Aula $aula): Aula
    {
        if (! $aula->publicada) {
            return $aula;
        }

        $aula->update([
            'publicada' => false,
        ]);
        // publicada_em permanece: substituir ou reimportar não republica sozinho.

        return $aula->fresh();
    }
}
