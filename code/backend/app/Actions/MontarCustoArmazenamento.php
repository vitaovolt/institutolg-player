<?php

namespace App\Actions;

use App\Models\Aula;

class MontarCustoArmazenamento
{
    /**
     * Estimativa interna Educraft (storage R2 Standard dos MP4). Capas fora.
     *
     * @return array{
     *     videos: int,
     *     bytes_videos: int,
     *     gb_videos: float,
     *     free_tier_gb: float,
     *     usd_por_gb: float,
     *     usd_storage_estimado: float,
     *     aviso: string
     * }
     */
    public function handle(): array
    {
        $bytes = (int) Aula::query()->importadas()->sum('tamanho_bytes');
        $gb = round($bytes / 1_000_000_000, 4);
        $free = (float) config('biblioteca.r2_storage_free_gb');
        $preco = (float) config('biblioteca.r2_storage_usd_por_gb');
        $cobravel = max(0.0, $gb - $free);

        return [
            'videos' => Aula::query()->importadas()->count(),
            'bytes_videos' => $bytes,
            'gb_videos' => $gb,
            'free_tier_gb' => $free,
            'usd_por_gb' => $preco,
            'usd_storage_estimado' => round($cobravel * $preco, 4),
            'aviso' => 'Estimativa só de storage R2 Standard dos vídeos (tamanho_bytes). Capas ficam de fora. Class A/B e a fatura real estão no painel Cloudflare.',
        ];
    }
}
