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
                if ($aula !== null && $aula->status_drive === 'enviando') {
                    $aula->update([
                        'status_drive' => 'pendente',
                    ]);
                }

                return;
            }

            $aula->update([
                'status_drive' => 'enviando',
            ]);

            $disk = Storage::disk((string) config('biblioteca.disk_aulas'));
            $streamVideo = null;
            $tamanhoVideo = null;
            $streamCapa = null;
            $tamanhoCapa = null;
            $extCapa = 'jpg';

            try {
                $enviarVideo = ! filled($aula->drive_file_id);
                if ($enviarVideo) {
                    $streamVideo = LerInicioDoArquivoDaBiblioteca::stream($aula->chave_play);
                    $tamanhoVideo = (int) $disk->size($aula->chave_play);
                }

                if (filled($aula->chave_capa) && $disk->exists($aula->chave_capa)) {
                    $extCapa = pathinfo((string) $aula->chave_capa, PATHINFO_EXTENSION) ?: 'jpg';
                    if (! filled($aula->drive_capa_file_id)) {
                        $streamCapa = LerInicioDoArquivoDaBiblioteca::stream($aula->chave_capa);
                        $tamanhoCapa = (int) $disk->size($aula->chave_capa);
                    }
                }

                $cliente->sincronizarAula($aula, $streamVideo, $tamanhoVideo, $streamCapa, $tamanhoCapa, $extCapa);
                $streamVideo = null;
                $streamCapa = null;
                $aula->refresh();
                $aula->update([
                    'status_drive' => 'ok',
                    'mensagem_erro' => null,
                ]);
            } catch (Throwable $e) {
                $aula->refresh();
                if (filled($aula->drive_file_id)) {
                    $aula->update([
                        'status_drive' => 'ok',
                        'mensagem_erro' => null,
                    ]);

                    return;
                }

                $aula->update([
                    'status_drive' => 'erro',
                    'mensagem_erro' => 'Não foi possível enviar a cópia para a pasta compartilhada. Tente de novo.',
                ]);

                throw $e;
            } finally {
                foreach ([$streamVideo, $streamCapa] as $aberto) {
                    if (is_resource($aberto)) {
                        fclose($aberto);
                    }
                }
            }
        });
    }
}
