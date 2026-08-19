<?php

namespace App\Actions;

use App\Models\Aula;
use App\Support\CaminhoDaBiblioteca;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IniciarSubstituicaoDaAula
{
    public function handle(Aula $aula): Aula
    {
        if ($aula->status_preparo === 'enviando' && filled($aula->token_upload)) {
            return $aula;
        }

        if ($aula->status_preparo === 'preparando') {
            throw new HttpException(422, 'A aula ainda está sendo preparada. Espere terminar para substituir o vídeo.');
        }

        if (! in_array($aula->status_preparo, ['pronta', 'erro'], true)) {
            throw new HttpException(422, 'Só é possível substituir o vídeo de uma aula pronta ou com erro.');
        }

        $tokenPublico = $aula->token_publico;
        $aula->loadMissing('disciplina.turma.curso');

        $aula->update([
            'status_preparo' => 'enviando',
            'status_drive' => 'pendente',
            'drive_file_id' => null,
            'mensagem_erro' => null,
            'chave_idempotencia' => (string) Str::uuid(),
            'token_upload' => Str::random(64),
            'chave_arquivo' => CaminhoDaBiblioteca::chaveVideo($aula->disciplina, (string) $aula->titulo),
            'token_publico' => $tokenPublico,
        ]);

        return $aula->fresh();
    }
}
