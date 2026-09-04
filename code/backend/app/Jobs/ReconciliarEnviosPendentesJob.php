<?php

namespace App\Jobs;

use App\Actions\ReconciliarEnviosPendentes;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconciliarEnviosPendentesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->onQueue((string) config('biblioteca.queue_preparo', 'biblioteca'));
    }

    public function uniqueId(): string
    {
        return 'reconciliar-envios';
    }

    public function handle(ReconciliarEnviosPendentes $reconciliar): void
    {
        $reconciliar->handle();
    }
}
