<?php

namespace App\Actions;

use App\Models\Aula;
use App\Support\LerInicioDoArquivoDaBiblioteca;
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

        try {
            $header = LerInicioDoArquivoDaBiblioteca::bytes($aula->chave_arquivo);
        } catch (\Throwable) {
            $aula->update([
                'status_preparo' => 'erro',
                'mensagem_erro' => 'Não encontramos o arquivo. Envie o export MP4 de novo.',
            ]);

            return $aula->fresh();
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

        return $aula->fresh();
    }
}
