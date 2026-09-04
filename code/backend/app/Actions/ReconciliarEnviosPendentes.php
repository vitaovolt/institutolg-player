<?php

namespace App\Actions;

use App\Models\Aula;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ReconciliarEnviosPendentes
{
    public function handle(): int
    {
        $ids = Aula::query()
            ->whereIn('status_preparo', ['enviando', 'erro'])
            ->whereNotNull('chave_arquivo')
            ->where('tamanho_bytes', '>', 0)
            ->pluck('id');

        $retomadas = 0;

        foreach ($ids as $id) {
            $aula = Aula::query()->find($id);
            if ($aula === null) {
                continue;
            }

            try {
                app(RetomarEnvioDaAula::class)->handle($aula);
                $retomadas++;
            } catch (HttpException) {
                continue;
            } catch (Throwable) {
                continue;
            }
        }

        return $retomadas;
    }
}
