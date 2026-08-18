<?php

namespace App\Actions;

use App\Models\Aula;
use App\Models\Disciplina;
use App\Support\CaminhoDaBiblioteca;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IniciarEnvioDaAula
{
    public function handle(Disciplina $disciplina, string $titulo, string $chaveIdempotencia): Aula
    {
        $existente = Aula::query()->where('chave_idempotencia', $chaveIdempotencia)->first();

        if ($existente !== null) {
            if ((int) $existente->disciplina_id !== (int) $disciplina->id) {
                throw new HttpException(409, 'Este envio já foi iniciado em outra disciplina.');
            }

            return $existente;
        }

        $disciplina->loadMissing('turma.curso');

        return $disciplina->aulas()->create([
            'titulo' => $titulo,
            'status_preparo' => 'enviando',
            'chave_idempotencia' => $chaveIdempotencia,
            'token_upload' => Str::random(64),
            'chave_arquivo' => CaminhoDaBiblioteca::chaveVideo($disciplina, $titulo),
        ]);
    }
}
