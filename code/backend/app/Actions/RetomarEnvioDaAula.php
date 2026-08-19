<?php

namespace App\Actions;

use App\Models\Aula;
use App\Support\LerInicioDoArquivoDaBiblioteca;
use App\Support\ValidarExportMp4;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RetomarEnvioDaAula
{
    public function handle(Aula $aula): Aula
    {
        if (! in_array($aula->status_preparo, ['enviando', 'erro'], true)) {
            throw new HttpException(422, 'Esta aula não está esperando o arquivo.');
        }

        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));

        if (! $aula->chave_arquivo || ! $disk->exists($aula->chave_arquivo)) {
            throw new HttpException(422, 'O arquivo ainda não chegou. Envie o MP4 de novo.');
        }

        $header = LerInicioDoArquivoDaBiblioteca::bytes($aula->chave_arquivo);
        if (! ValidarExportMp4::pareceMp4($header)) {
            throw new HttpException(422, ValidarExportMp4::mensagemRecusa());
        }

        $tamanho = (int) $disk->size($aula->chave_arquivo);
        $max = (int) config('biblioteca.upload_max_bytes');
        if ($tamanho > $max) {
            throw new HttpException(422, ValidarExportMp4::mensagemGrandeDemais());
        }

        $aula->update([
            'tamanho_bytes' => $tamanho,
            's3_upload_id' => null,
            'mensagem_erro' => null,
        ]);

        return app(ConcluirEnvioDaAula::class)->handle($aula->fresh());
    }
}
