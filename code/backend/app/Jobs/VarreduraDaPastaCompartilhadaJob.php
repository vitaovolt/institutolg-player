<?php

namespace App\Jobs;

use App\Actions\VarrerPastaCompartilhada;
use App\Services\Integrations\ClientePastaDrive;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VarreduraDaPastaCompartilhadaJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue((string) config('biblioteca.queue_preparo', 'biblioteca'));
    }

    public function uniqueId(): string
    {
        return 'importar-pasta';
    }

    public function handle(VarrerPastaCompartilhada $varrer, ClientePastaDrive $cliente): void
    {
        if (! $cliente->podeListarPasta()) {
            return;
        }

        $varrer->handle($cliente);
    }
}