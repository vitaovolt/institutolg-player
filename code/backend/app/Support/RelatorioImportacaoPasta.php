<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class RelatorioImportacaoPasta
{
    public const CACHE_KEY = 'importar-pasta:relatorio';

    public const LOCK = 'importar-pasta';

    /**
     * @return array{
     *     status: string,
     *     iniciado_em: ?string,
     *     terminado_em: ?string,
     *     criados: list<array{tipo: string, nome: string}>,
     *     ligados: list<array{tipo: string, nome: string}>,
     *     enfileirados: int,
     *     ignorados: list<array{item: string, motivo: string}>,
     *     erros: list<string>
     * }
     */
    public static function vazio(string $status = 'ocioso'): array
    {
        return [
            'status' => $status,
            'iniciado_em' => null,
            'terminado_em' => null,
            'criados' => [],
            'ligados' => [],
            'enfileirados' => 0,
            'ignorados' => [],
            'erros' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ler(): array
    {
        $salvo = Cache::get(self::CACHE_KEY);

        return is_array($salvo) ? array_merge(self::vazio(), $salvo) : self::vazio();
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function gravar(array $dados): void
    {
        Cache::put(self::CACHE_KEY, $dados, now()->addDay());
    }

    /**
     * @return array<string, mixed>
     */
    public static function iniciar(): array
    {
        $dados = self::vazio('importando');
        $dados['iniciado_em'] = now()->toIso8601String();
        self::gravar($dados);

        return $dados;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function concluir(array $dados, string $status = 'ok'): void
    {
        $dados['status'] = $status;
        $dados['terminado_em'] = now()->toIso8601String();
        self::gravar($dados);
    }

    public static function emAndamento(): bool
    {
        return self::ler()['status'] === 'importando';
    }
}