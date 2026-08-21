<?php

namespace App\Actions;

use App\Models\Aula;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AtualizarAula
{
    public function __construct(private SincronizarAulaComDrive $sincronizarDrive) {}

    /**
     * @param  array{titulo?: string, ordem?: int}  $dados
     */
    public function handle(Aula $aula, array $dados): Aula
    {
        $tituloMudou = false;

        $aula = Cache::lock('aula-envio:'.$aula->id, 20)->block(10, function () use ($aula, $dados, &$tituloMudou): Aula {
            $aula->refresh();

            $tituloAnterior = (string) $aula->titulo;
            $tituloNovo = array_key_exists('titulo', $dados) ? (string) $dados['titulo'] : $tituloAnterior;
            $tituloMudou = $tituloNovo !== $tituloAnterior;

            $aula->update([
                'titulo' => $tituloNovo,
                'ordem' => $dados['ordem'] ?? $aula->ordem,
            ]);

            return $aula->fresh();
        });

        if ($tituloMudou) {
            $this->enfileirarCopiaSePronta($aula);
        }

        return $aula->fresh();
    }

    private function enfileirarCopiaSePronta(Aula $aula): void
    {
        if (! $aula->estaProntaParaAssistir()) {
            return;
        }

        try {
            $this->sincronizarDrive->handle($aula);
        } catch (HttpException) {
            // rascunho ou ainda sem play — o nome no cadastro já foi salvo
        }
    }
}
