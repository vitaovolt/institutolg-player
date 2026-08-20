<?php

namespace App\Actions;

use App\Contracts\AssinadorDeUploadDireto;
use App\Models\Aula;
use App\Support\CaminhoDaBiblioteca;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class IniciarSubstituicaoDaAula
{
    public function __construct(private AssinadorDeUploadDireto $assinador) {}

    public function handle(Aula $aula): Aula
    {
        return Cache::lock('aula-envio:'.$aula->id, 20)->block(10, function () use ($aula): Aula {
            $aula->refresh();

            if ($aula->status_preparo === 'preparando') {
                throw new HttpException(422, 'A aula ainda está sendo preparada. Espere terminar para substituir o vídeo.');
            }

            if (! in_array($aula->status_preparo, ['pronta', 'erro', 'enviando'], true)) {
                throw new HttpException(422, 'Só é possível substituir o vídeo de uma aula pronta, com erro ou com envio parado.');
            }

            try {
                $this->assinador->abortar($aula);
            } catch (Throwable) {
                // envio incompleto: a aula precisa de um novo token mesmo assim
            }

            $tokenPublico = $aula->token_publico;
            $aula->loadMissing('disciplina.turma.curso');

            $aula->update([
                'status_preparo' => 'enviando',
                'status_drive' => 'pendente',
                'mensagem_erro' => null,
                's3_upload_id' => null,
                'chave_idempotencia' => (string) Str::uuid(),
                'token_upload' => Str::random(64),
                'chave_arquivo' => CaminhoDaBiblioteca::chaveVideo($aula->disciplina, (string) $aula->titulo),
                'token_publico' => $tokenPublico,
            ]);

            return $aula->fresh();
        });
    }
}
