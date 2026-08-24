<?php

namespace App\Actions;

use App\Models\Aula;
use App\Models\Disciplina;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MoverAula
{
    public function __construct(private SincronizarAulaComDrive $sincronizarDrive) {}

    public function handle(Aula $aula, Disciplina $destino): Aula
    {
        $aula = Cache::lock('aula-envio:'.$aula->id, 20)->block(10, function () use ($aula, $destino): Aula {
            $aula->refresh();
            $destino->refresh();

            if (in_array($aula->status_preparo, ['enviando', 'preparando'], true)) {
                throw new HttpException(422, 'Espere o envio terminar para mover a aula.');
            }

            if ((int) $aula->disciplina_id === (int) $destino->id) {
                throw new HttpException(422, 'A aula já está nesta disciplina.');
            }

            $tituloDuplicado = Aula::query()
                ->where('disciplina_id', $destino->id)
                ->where('titulo', $aula->titulo)
                ->whereKeyNot($aula->id)
                ->exists();

            if ($tituloDuplicado) {
                throw new HttpException(422, 'Já existe uma aula com este título nesta disciplina.');
            }

            $ordem = (int) Aula::query()->where('disciplina_id', $destino->id)->max('ordem') + 1;

            $aula->update([
                'disciplina_id' => $destino->id,
                'ordem' => $ordem,
            ]);

            return $aula->fresh(['disciplina.turma.curso']);
        });

        $this->enfileirarCopiaSePronta($aula);

        return $aula->fresh(['disciplina.turma.curso']);
    }

    private function enfileirarCopiaSePronta(Aula $aula): void
    {
        if (! $aula->estaProntaParaAssistir()) {
            return;
        }

        try {
            $this->sincronizarDrive->handle($aula);
        } catch (HttpException) {
            // cadastro já mudou; a pasta compartilhada tenta de novo depois
        }
    }
}
