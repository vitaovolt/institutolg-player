<?php

namespace App\Actions;

use App\Models\Disciplina;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExcluirDisciplina
{
    public function handle(Disciplina $disciplina): void
    {
        if ($disciplina->aulas()->exists()) {
            throw new HttpException(
                409,
                'Não dá para excluir: esta disciplina tem aulas. Remova as aulas antes.',
            );
        }

        $disciplina->delete();
    }
}
