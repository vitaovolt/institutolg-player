<?php

namespace App\Actions;

use App\Models\Turma;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExcluirTurma
{
    public function handle(Turma $turma): void
    {
        if ($turma->disciplinas()->exists()) {
            throw new HttpException(
                409,
                'Não dá para excluir: esta turma tem disciplinas. Remova as disciplinas antes.',
            );
        }

        $turma->delete();
    }
}
