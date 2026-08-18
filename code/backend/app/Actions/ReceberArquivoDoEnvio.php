<?php

namespace App\Actions;

use App\Models\Aula;
use App\Support\ValidarExportMp4;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReceberArquivoDoEnvio
{
    public function handle(string $token, string $binario): Aula
    {
        $aula = Aula::query()->where('token_upload', $token)->first();

        if ($aula === null) {
            throw new HttpException(404, 'Envio não encontrado ou já concluído.');
        }

        if (! in_array($aula->status_preparo, ['enviando', 'erro'], true)) {
            throw new HttpException(409, 'Este envio não aceita mais arquivo.');
        }

        if ($binario === '') {
            throw new HttpException(422, ValidarExportMp4::mensagemRecusa());
        }

        $max = (int) config('biblioteca.upload_max_bytes');

        if (strlen($binario) > $max) {
            throw new HttpException(422, ValidarExportMp4::mensagemGrandeDemais());
        }

        if (! ValidarExportMp4::pareceMp4($binario)) {
            throw new HttpException(422, ValidarExportMp4::mensagemRecusa());
        }

        $disk = (string) config('biblioteca.disk_aulas');
        Storage::disk($disk)->put($aula->chave_arquivo, $binario);

        $aula->update([
            'tamanho_bytes' => strlen($binario),
            'mensagem_erro' => null,
        ]);

        return $aula->fresh();
    }
}
