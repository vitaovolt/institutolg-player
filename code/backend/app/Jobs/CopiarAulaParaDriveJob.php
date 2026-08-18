<?php

namespace App\Jobs;

use App\Models\Aula;
use App\Services\Integrations\ClientePastaDrive;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CopiarAulaParaDriveJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public int $uniqueFor = 600;

    public function __construct(public int $aulaId)
    {
        $this->onQueue((string) config('biblioteca.queue_preparo', 'biblioteca'));
    }

    public function uniqueId(): string
    {
        return 'drive-'.$this->aulaId;
    }

    public function handle(ClientePastaDrive $cliente): void
    {
        Cache::lock('aula-drive:'.$this->aulaId, 120)->block(10, function () use ($cliente): void {
            $aula = Aula::query()->find($this->aulaId);

            if ($aula === null || ! $aula->estaProntaParaAssistir()) {
                return;
            }

            if ($aula->status_drive === 'ok') {
                return;
            }

            $aula->update([
                'status_drive' => 'enviando',
            ]);

            $disk = Storage::disk((string) config('biblioteca.disk_aulas'));

            try {
                $conteudo = $disk->get($aula->chave_play);
                $cliente->enviarCopia($aula, $conteudo);
                $aula->update([
                    'status_drive' => 'ok',
                    'mensagem_erro' => null,
                ]);
            } catch (Throwable $e) {
                $aula->update([
                    'status_drive' => 'erro',
                    'mensagem_erro' => 'Não foi possível enviar a cópia para a pasta compartilhada. Tente de novo.',
                ]);

                throw $e;
            }
        });
    }
}
