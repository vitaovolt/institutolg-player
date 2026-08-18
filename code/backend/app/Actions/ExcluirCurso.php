<?php

namespace App\Actions;

use App\Models\Curso;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExcluirCurso
{
    public function handle(Curso $curso): void
    {
        if ($curso->turmas()->exists()) {
            throw new HttpException(
                409,
                'Não dá para excluir: este curso tem turmas. Remova as turmas antes.',
            );
        }

        $curso->delete();
    }
}
