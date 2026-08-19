<?php

namespace App\Actions;

use App\Models\Aula;
use Illuminate\Support\Carbon;

class MontarResumoDoMes
{
    /**
     * @return array{
     *     competencia: string,
     *     enviadas: int,
     *     publicadas: int,
     *     enviadas_nao_publicadas: int,
     *     total_importadas: int,
     *     aulas_por_mes: list<array{competencia: string, enviadas: int}>,
     *     mensalidade_painel: float,
     *     preco_aula_publicada: float,
     *     valor_aulas_publicadas: float,
     *     total: float
     * }
     */
    public function handle(?Carbon $referencia = null): array
    {
        $ref = $referencia ?? now();
        $inicio = $ref->copy()->startOfMonth();
        $fim = $ref->copy()->endOfMonth();

        $enviadas = Aula::query()->enviadasNoMes($inicio, $fim)->count();
        $publicadas = Aula::query()->publicadas()->count();
        $enviadasNaoPublicadas = Aula::query()
            ->enviadasNoMes($inicio, $fim)
            ->where('publicada', false)
            ->count();
        $totalImportadas = Aula::query()->importadas()->count();

        $painel = (float) config('biblioteca.mensalidade_painel');
        $preco = (float) config('biblioteca.preco_aula_publicada');
        $valorAulas = round($publicadas * $preco, 2);

        return [
            'competencia' => $inicio->format('Y-m'),
            'enviadas' => $enviadas,
            'publicadas' => $publicadas,
            'enviadas_nao_publicadas' => $enviadasNaoPublicadas,
            'total_importadas' => $totalImportadas,
            'aulas_por_mes' => $this->aulasPorMes($inicio),
            'mensalidade_painel' => $painel,
            'preco_aula_publicada' => $preco,
            'valor_aulas_publicadas' => $valorAulas,
            'total' => round($painel + $valorAulas, 2),
        ];
    }

    /**
     * @return list<array{competencia: string, enviadas: int}>
     */
    private function aulasPorMes(Carbon $fimMes): array
    {
        $primeiro = $fimMes->copy()->subMonths(11)->startOfMonth();
        $ultimo = $fimMes->copy()->endOfMonth();

        $mapa = Aula::query()
            ->importadas()
            ->whereBetween('enviado_em', [$primeiro, $ultimo])
            ->get(['enviado_em'])
            ->groupBy(fn (Aula $aula) => $aula->enviado_em->format('Y-m'))
            ->map->count();

        $serie = [];
        $cursor = $primeiro->copy();
        for ($i = 0; $i < 12; $i++) {
            $chave = $cursor->format('Y-m');
            $serie[] = [
                'competencia' => $chave,
                'enviadas' => (int) ($mapa[$chave] ?? 0),
            ];
            $cursor->addMonth();
        }

        return $serie;
    }
}
