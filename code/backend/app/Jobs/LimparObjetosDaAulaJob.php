<?php

namespace App\Jobs;

use App\Contracts\AssinadorDeUploadDireto;
use App\Models\Aula;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LimparObjetosDaAulaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  array{s3_upload_id:?string,chave_arquivo:?string,chave_play:?string,chave_capa:?string}  $snapshot
     */
    public function __construct(public array $snapshot)
    {
        $this->onQueue((string) config('biblioteca.queue_preparo', 'biblioteca'));
    }

    public function handle(AssinadorDeUploadDireto $assinador): void
    {
        $aula = new Aula;
        $aula->forceFill([
            's3_upload_id' => $this->snapshot['s3_upload_id'] ?? null,
            'chave_arquivo' => $this->snapshot['chave_arquivo'] ?? null,
        ]);

        try {
            $assinador->abortar($aula);
        } catch (Throwable) {
            // envio incompleto ou já fechado
        }

        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));
        foreach (['chave_arquivo', 'chave_play', 'chave_capa'] as $campo) {
            $chave = $this->snapshot[$campo] ?? null;
            if (! filled($chave)) {
                continue;
            }
            try {
                $disk->delete($chave);
            } catch (Throwable) {
                // objeto ausente ou envio multipart ainda aberto
            }
        }
    }
}
