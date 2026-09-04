<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('biblioteca:fila', function () {
    $fila = (string) config('biblioteca.queue_preparo', 'biblioteca');

    $this->newLine();
    $this->info('Fila da biblioteca — deixe este terminal aberto.');
    $this->line("Ouvindo a fila «{$fila}». O prompt não volta: isso é o worker esperando.");
    $this->line('Silêncio agora é normal. Depois de enviar um MP4, aparece Processing neste terminal.');
    $this->line('Tela em Pronta sem Processing aqui = outro worker antigo pegou o job (a aula mesmo assim está ok).');
    $this->line('Ctrl+C só quando terminar o teste.');
    $this->newLine();

    $this->call('queue:work', [
        '--queue' => $fila,
        '--tries' => 3,
        '--sleep' => 1,
        '--verbose' => true,
    ]);
})->purpose('Worker da fila da biblioteca. Terminal fica ocupado de propósito.');

Artisan::command('biblioteca:retomar-envio {aula}', function () {
    $id = (int) $this->argument('aula');
    $aula = \App\Models\Aula::query()->find($id);
    if ($aula === null) {
        $this->error('Aula não encontrada.');

        return 1;
    }

    try {
        $aula = app(\App\Actions\RetomarEnvioDaAula::class)->handle($aula);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        $this->error($e->getMessage());

        return 1;
    }
    $this->info("Aula {$aula->id}: {$aula->status_preparo}");

    return 0;
})->purpose('Retoma envio quando o arquivo já está no objeto e a aula ficou em enviando.');

Artisan::command('biblioteca:reconciliar-envios', function () {
    $n = app(\App\Actions\ReconciliarEnviosPendentes::class)->handle();
    $this->info($n === 0 ? 'Nenhuma aula presa para retomar.' : "Retomadas: {$n}");

    return 0;
})->purpose('Retoma aulas em enviando/erro cujo arquivo já está no objeto.');
