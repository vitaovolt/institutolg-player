<?php

namespace App\Jobs;

use App\Models\Aula;
use App\Services\Integrations\ClientePastaDrive;
use App\Support\LerInicioDoArquivoDaBiblioteca;
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
    public array $backoff = [60, 300, 900];

    public int $timeout = 43200;

    public int $uniqueFor = 43200;

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
        Cache::lock('aula-drive:'.$this->aulaId, 43200)->block(10, function () use ($cliente): void {
            $aula = Aula::query()->with(['disciplina.turma.curso'])->find($this->aulaId);

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
                $stream = LerInicioDoArquivoDaBiblioteca::stream($aula->chave_play);
                $tamanho = (int) $disk->size($aula->chave_play);
                $cliente->enviarCopia($aula, $stream, $tamanho, 'video', 'mp4');

                if (filled($aula->chave_capa) && $disk->exists($aula->chave_capa)) {
                    $capa = LerInicioDoArquivoDaBiblioteca::stream($aula->chave_capa);
                    $ext = pathinfo((string) $aula->chave_capa, PATHINFO_EXTENSION) ?: 'jpg';
                    $cliente->enviarCopia($aula, $capa, (int) $disk->size($aula->chave_capa), 'capa', $ext);
                }
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
