<?php

namespace App\Jobs;

use App\Actions\PrepararVersaoDaAula;
use App\Models\Aula;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class PrepararVersaoDaAulaJob implements ShouldQueue, ShouldBeUnique
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
        return (string) $this->aulaId;
    }

    public function handle(PrepararVersaoDaAula $preparar): void
    {
        Cache::lock('aula-preparar:'.$this->aulaId, 120)->block(10, function () use ($preparar): void {
            $aula = Aula::query()->find($this->aulaId);

            if ($aula === null) {
                return;
            }

            $preparar->handle($aula);
        });
    }
}
