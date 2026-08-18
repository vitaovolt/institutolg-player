<?php

namespace App\Actions;

use App\Contracts\AssinadorDeUploadDireto;
use App\Models\Aula;
use App\Support\ModoEnvioArquivo;
use App\Support\ValidarExportMp4;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CompletarPartesDoEnvio
{
    public function __construct(private AssinadorDeUploadDireto $assinador) {}

    /**
     * @param  list<array{part_number: int, etag: string}>  $partes
     */
    public function handle(string $token, array $partes): Aula
    {
        if (! ModoEnvioArquivo::usaMultipart()) {
            throw new HttpException(409, 'Este envio não usa partes. Envie o MP4 no PUT único.');
        }

        $aula = Aula::query()->where('token_upload', $token)->first();

        if ($aula === null) {
            throw new HttpException(404, 'Envio não encontrado ou já concluído.');
        }

        if (! in_array($aula->status_preparo, ['enviando', 'erro'], true)) {
            throw new HttpException(409, 'Este envio não aceita mais arquivo.');
        }

        if (blank($aula->s3_upload_id)) {
            throw new HttpException(422, 'O envio das partes ainda não começou.');
        }

        $this->assinador->completar($aula, $partes);

        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));

        if (! $disk->exists($aula->chave_arquivo)) {
            throw new HttpException(422, 'O arquivo ainda não chegou. Envie o MP4 de novo.');
        }

        $stream = $disk->readStream($aula->chave_arquivo);
        $header = $stream ? (string) fread($stream, 32) : '';
        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! ValidarExportMp4::pareceMp4($header)) {
            $disk->delete($aula->chave_arquivo);
            throw new HttpException(422, ValidarExportMp4::mensagemRecusa());
        }

        $tamanho = (int) $disk->size($aula->chave_arquivo);
        $max = (int) config('biblioteca.upload_max_bytes');

        if ($tamanho > $max) {
            $disk->delete($aula->chave_arquivo);
            throw new HttpException(422, ValidarExportMp4::mensagemGrandeDemais());
        }

        $aula->update([
            'tamanho_bytes' => $tamanho,
            's3_upload_id' => null,
            'mensagem_erro' => null,
        ]);

        return $aula->fresh();
    }
}
