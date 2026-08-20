<?php

namespace App\Actions;

use App\Contracts\AssinadorDeUploadDireto;
use App\Models\Aula;
use App\Support\LerInicioDoArquivoDaBiblioteca;
use App\Support\ValidarExportMp4;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class FecharMultipartDoEnvio
{
    public function __construct(private AssinadorDeUploadDireto $assinador) {}

    /**
     * @param  list<array{part_number: int, etag: string}>  $partes
     */
    public function handle(Aula $aula, array $partes): Aula
    {
        $aula->refresh();

        if (blank($aula->s3_upload_id) && $aula->chave_arquivo && $this->objetoExiste($aula)) {
            return $this->marcarArquivoChegou($aula);
        }

        if (blank($aula->s3_upload_id)) {
            throw new HttpException(422, 'O envio das partes ainda não começou.');
        }

        try {
            $this->assinador->completar($aula, $partes);
        } catch (Throwable $e) {
            if (! $this->objetoExiste($aula)) {
                throw $e;
            }
        }

        if (! $this->objetoExiste($aula)) {
            throw new HttpException(422, 'O arquivo ainda não chegou. Envie o MP4 de novo.');
        }

        return $this->marcarArquivoChegou($aula);
    }

    private function objetoExiste(Aula $aula): bool
    {
        if (! $aula->chave_arquivo) {
            return false;
        }

        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));

        try {
            return $disk->exists($aula->chave_arquivo);
        } catch (Throwable) {
            return false;
        }
    }

    private function marcarArquivoChegou(Aula $aula): Aula
    {
        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));

        try {
            $header = LerInicioDoArquivoDaBiblioteca::bytes($aula->chave_arquivo);
        } catch (Throwable) {
            throw new HttpException(503, 'O arquivo chegou, mas não deu para conferir agora. Tente de novo.');
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
