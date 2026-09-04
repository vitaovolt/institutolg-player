<?php

namespace App\Jobs;

use App\Actions\ConcluirEnvioDaAula;
use App\Actions\FecharMultipartDoEnvio;
use App\Models\Aula;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CompletarMultipartDaAulaJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public function __construct(public int $aulaId)
    {
        $this->onQueue((string) config('biblioteca.queue_preparo', 'biblioteca'));
    }

    public function uniqueId(): string
    {
        return 'multipart-'.$this->aulaId;
    }

    public function handle(FecharMultipartDoEnvio $fechar, ConcluirEnvioDaAula $concluir): void
    {
        $aula = Aula::query()->find($this->aulaId);
        if ($aula === null) {
            return;
        }

        /** @var list<array{part_number: int, etag: string}> $partes */
        $partes = Cache::get('aula-multipart:'.$this->aulaId, []);

        try {
            $aula = $fechar->handle($aula, is_array($partes) ? $partes : []);
            Cache::forget('aula-multipart:'.$this->aulaId);
        } catch (Throwable $e) {
            $aula->refresh();
            if (blank($aula->s3_upload_id) && (int) $aula->tamanho_bytes > 0) {
                Cache::forget('aula-multipart:'.$this->aulaId);
                $this->concluirArquivoChegou($concluir, $aula);

                return;
            }

            $aula->update([
                'mensagem_erro' => 'Não foi possível fechar o envio do arquivo. Tente de novo.',
            ]);

            throw $e;
        }

        $this->concluirArquivoChegou($concluir, $aula);
    }

    private function concluirArquivoChegou(ConcluirEnvioDaAula $concluir, Aula $aula): void
    {
        $aula->refresh();
        if (! in_array($aula->status_preparo, ['enviando', 'erro'], true)) {
            return;
        }

        $concluir->handle($aula);
    }
}
