<?php

namespace App\Actions;

use App\Jobs\CopiarAulaParaDriveJob;
use App\Models\Aula;
use App\Support\ValidarExportMp4;
use Illuminate\Support\Facades\Storage;

class PrepararVersaoDaAula
{
    public function handle(Aula $aula): Aula
    {
        if ($aula->status_preparo === 'pronta' && filled($aula->chave_play)) {
            return $aula;
        }

        $diskName = (string) config('biblioteca.disk_aulas');
        $disk = Storage::disk($diskName);

        if (! $aula->chave_arquivo || ! $disk->exists($aula->chave_arquivo)) {
            $aula->update([
                'status_preparo' => 'erro',
                'mensagem_erro' => 'Não encontramos o arquivo. Envie o export MP4 de novo.',
            ]);

            return $aula->fresh();
        }

        $stream = $disk->readStream($aula->chave_arquivo);
        $header = $stream ? (string) fread($stream, 32) : '';
        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! ValidarExportMp4::pareceMp4($header)) {
            $aula->update([
                'status_preparo' => 'erro',
                'mensagem_erro' => ValidarExportMp4::mensagemRecusa(),
            ]);

            return $aula->fresh();
        }

        $playAnterior = $aula->chave_play;
        $play = $aula->chave_arquivo;

        $aula->update([
            'chave_play' => $play,
            'status_preparo' => 'pronta',
            'mensagem_erro' => null,
            'status_drive' => 'pendente',
        ]);

        if ($playAnterior && $playAnterior !== $play && $disk->exists($playAnterior)) {
            $disk->delete($playAnterior);
        }

        CopiarAulaParaDriveJob::dispatch($aula->id);

        return $aula->fresh();
    }
}
