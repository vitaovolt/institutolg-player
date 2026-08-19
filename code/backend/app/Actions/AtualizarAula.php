<?php

namespace App\Actions;

use App\Models\Aula;
use App\Services\Integrations\ClientePastaDrive;
use App\Support\CaminhoDaBiblioteca;
use App\Support\MoverArquivoDaBiblioteca;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AtualizarAula
{
    public function __construct(private ClientePastaDrive $pastaDrive) {}

    /**
     * @param  array{titulo?: string, ordem?: int}  $dados
     */
    public function handle(Aula $aula, array $dados): Aula
    {
        return Cache::lock('aula-envio:'.$aula->id, 600)->block(10, function () use ($aula, $dados): Aula {
            $aula->refresh()->loadMissing('disciplina.turma.curso');

            $tituloAnterior = (string) $aula->titulo;
            $tituloNovo = array_key_exists('titulo', $dados) ? (string) $dados['titulo'] : $tituloAnterior;
            $chavesNovas = $this->chavesPara($aula, $tituloNovo);
            $chavesAntigas = [
                'chave_arquivo' => $aula->chave_arquivo,
                'chave_play' => $aula->chave_play,
                'chave_capa' => $aula->chave_capa,
            ];

            $this->moverNoPlay($chavesAntigas, $chavesNovas);

            try {
                $aula->update([
                    'titulo' => $tituloNovo,
                    'ordem' => $dados['ordem'] ?? $aula->ordem,
                    'chave_arquivo' => $chavesNovas['chave_arquivo'],
                    'chave_play' => $chavesNovas['chave_play'],
                    'chave_capa' => $chavesNovas['chave_capa'],
                ]);
            } catch (Throwable $e) {
                $this->moverNoPlay($chavesNovas, $chavesAntigas);
                throw $e;
            }

            if ($tituloAnterior !== $tituloNovo) {
                $this->tentarRenomearNaPastaCompartilhada($aula->fresh(['disciplina.turma.curso']), $tituloAnterior);
            }

            return $aula->fresh();
        });
    }

    /**
     * @return array{chave_arquivo: ?string, chave_play: ?string, chave_capa: ?string}
     */
    private function chavesPara(Aula $aula, string $titulo): array
    {
        $video = CaminhoDaBiblioteca::chaveVideo($aula->disciplina, $titulo);
        $arquivo = filled($aula->chave_arquivo) ? $video : $aula->chave_arquivo;
        $play = $aula->chave_play;
        if (filled($play)) {
            $play = ($play === $aula->chave_arquivo) ? $arquivo : $video;
        }
        $capa = $aula->chave_capa;
        if (filled($capa)) {
            $ext = pathinfo((string) $capa, PATHINFO_EXTENSION) ?: 'jpg';
            $capa = CaminhoDaBiblioteca::chaveCapaPara($aula->disciplina, $titulo, $ext);
        }

        return [
            'chave_arquivo' => $arquivo,
            'chave_play' => $play,
            'chave_capa' => $capa,
        ];
    }

    /**
     * @param  array{chave_arquivo: ?string, chave_play: ?string, chave_capa: ?string}  $de
     * @param  array{chave_arquivo: ?string, chave_play: ?string, chave_capa: ?string}  $para
     */
    private function moverNoPlay(array $de, array $para): void
    {
        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));
        $pares = [];
        foreach (['chave_arquivo', 'chave_play', 'chave_capa'] as $campo) {
            $origem = $de[$campo] ?? null;
            $destino = $para[$campo] ?? null;
            if (! filled($origem) || ! filled($destino) || $origem === $destino) {
                continue;
            }
            $pares[$origem] = $destino;
        }

        $feitos = [];
        try {
            foreach ($pares as $origem => $destino) {
                MoverArquivoDaBiblioteca::seExistir($disk, $origem, $destino);
                $feitos[] = [$origem, $destino];
            }
        } catch (Throwable $e) {
            foreach (array_reverse($feitos) as [$origem, $destino]) {
                try {
                    MoverArquivoDaBiblioteca::seExistir($disk, $destino, $origem);
                } catch (Throwable) {
                    // tenta devolver o que já moveu
                }
            }
            throw $e;
        }
    }

    private function tentarRenomearNaPastaCompartilhada(Aula $aula, string $tituloAnterior): void
    {
        if (! $this->pastaDrive->copiaAtiva()) {
            return;
        }

        try {
            $this->pastaDrive->renomearCopia($aula, $tituloAnterior);
        } catch (Throwable $e) {
            Log::warning('Não foi possível atualizar o nome na pasta compartilhada.', [
                'aula_id' => $aula->id,
                'motivo' => $e::class,
            ]);
        }
    }
}
