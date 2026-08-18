<?php

namespace App\Actions;

use App\Models\Aula;
use App\Support\ValidarExportMp4;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReceberArquivoDoEnvio
{
    public function handle(string $token, mixed $entrada): Aula
    {
        $aula = Aula::query()->where('token_upload', $token)->first();

        if ($aula === null) {
            throw new HttpException(404, 'Envio não encontrado ou já concluído.');
        }

        if (! in_array($aula->status_preparo, ['enviando', 'erro'], true)) {
            throw new HttpException(409, 'Este envio não aceita mais arquivo.');
        }

        if (is_resource($entrada)) {
            $this->gravarStream($aula, $entrada);
        } else {
            $this->gravarString($aula, (string) $entrada);
        }

        return $aula->fresh();
    }

    private function gravarString(Aula $aula, string $binario): void
    {
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
    }

    /**
     * @param  resource  $entrada
     */
    private function gravarStream(Aula $aula, $entrada): void
    {
        $header = fread($entrada, 32);

        if ($header === false || $header === '') {
            throw new HttpException(422, ValidarExportMp4::mensagemRecusa());
        }

        if (! ValidarExportMp4::pareceMp4($header)) {
            throw new HttpException(422, ValidarExportMp4::mensagemRecusa());
        }

        $tmp = fopen('php://temp/maxmemory:8388608', 'w+b');
        fwrite($tmp, $header);
        stream_copy_to_stream($entrada, $tmp);
        $tamanho = (int) ftell($tmp);
        $max = (int) config('biblioteca.upload_max_bytes');

        if ($tamanho > $max) {
            fclose($tmp);
            throw new HttpException(422, ValidarExportMp4::mensagemGrandeDemais());
        }

        rewind($tmp);
        $disk = (string) config('biblioteca.disk_aulas');
        Storage::disk($disk)->writeStream($aula->chave_arquivo, $tmp);
        fclose($tmp);

        $aula->update([
            'tamanho_bytes' => $tamanho,
            'mensagem_erro' => null,
        ]);
    }
}
