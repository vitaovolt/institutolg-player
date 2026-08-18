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

        $painel = (float) config('biblioteca.mensalidade_painel');
        $preco = (float) config('biblioteca.preco_aula_publicada');
        $valorAulas = round($publicadas * $preco, 2);

        return [
            'competencia' => $inicio->format('Y-m'),
            'enviadas' => $enviadas,
            'publicadas' => $publicadas,
            'enviadas_nao_publicadas' => $enviadasNaoPublicadas,
            'mensalidade_painel' => $painel,
            'preco_aula_publicada' => $preco,
            'valor_aulas_publicadas' => $valorAulas,
            'total' => round($painel + $valorAulas, 2),
        ];
    }
}
