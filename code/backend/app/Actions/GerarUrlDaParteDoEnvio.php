<?php

namespace App\Actions;

use App\Contracts\AssinadorDeUploadDireto;
use App\Models\Aula;
use App\Support\ModoEnvioArquivo;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GerarUrlDaParteDoEnvio
{
    public function __construct(private AssinadorDeUploadDireto $assinador) {}

    public function handle(string $token, int $parte): string
    {
        if (! ModoEnvioArquivo::usaMultipart()) {
            throw new HttpException(409, 'Este envio não usa partes. Envie o MP4 no PUT único.');
        }

        if ($parte < 1 || $parte > 10000) {
            throw new HttpException(422, 'Número de parte inválido.');
        }

        $aula = Aula::query()->where('token_upload', $token)->first();

        if ($aula === null) {
            throw new HttpException(404, 'Envio não encontrado ou já concluído.');
        }

        if (! in_array($aula->status_preparo, ['enviando', 'erro'], true)) {
            throw new HttpException(409, 'Este envio não aceita mais arquivo.');
        }

        if (blank($aula->s3_upload_id)) {
            $aula->update(['s3_upload_id' => $this->assinador->iniciar($aula)]);
            $aula->refresh();
        }

        return $this->assinador->urlDaParte($aula, $parte);
    }
}
