<?php

namespace App\Support;

use App\Models\Aula;
use App\Models\Disciplina;
use Illuminate\Support\Str;

class CaminhoDaBiblioteca
{
    public static function segmento(string $nome): string
    {
        $slug = Str::slug($nome, '-', 'pt');

        return $slug !== '' ? $slug : 'sem-nome';
    }

    public static function prefixoDaDisciplina(Disciplina $disciplina): string
    {
        $disciplina->loadMissing('turma.curso');

        return self::segmento((string) $disciplina->turma?->curso?->nome)
            .'/'.self::segmento((string) $disciplina->turma?->nome)
            .'/'.self::segmento((string) $disciplina->nome);
    }

    public static function prefixo(Aula $aula): string
    {
        $aula->loadMissing('disciplina.turma.curso');

        return self::prefixoDaDisciplina($aula->disciplina);
    }

    public static function chaveVideo(Disciplina $disciplina, string $titulo): string
    {
        return self::prefixoDaDisciplina($disciplina).'/'.self::segmento($titulo).'.mp4';
    }

    public static function chaveCapaPara(Disciplina $disciplina, string $titulo, string $extensao): string
    {
        $ext = strtolower(ltrim($extensao, '.'));
        if ($ext === '') {
            $ext = 'jpg';
        }

        return self::prefixoDaDisciplina($disciplina).'/'.self::segmento($titulo).'_capa.'.$ext;
    }

    public static function chaveCapa(Aula $aula, string $extensao): string
    {
        $aula->loadMissing('disciplina.turma.curso');

        return self::chaveCapaPara($aula->disciplina, (string) $aula->titulo, $extensao);
    }

    public static function nomeArquivoDrivePara(string $titulo, string $tipo = 'video', string $extensao = 'mp4'): string
    {
        $base = str_replace(['/', '\\'], '-', trim($titulo));
        if ($base === '') {
            $base = 'aula';
        }
        $ext = strtolower(ltrim($extensao, '.'));
        if ($tipo === 'capa') {
            return $base.'_capa.'.$ext;
        }

        return $base.'.mp4';
    }

    public static function nomeArquivoDrive(Aula $aula, string $tipo = 'video', string $extensao = 'mp4'): string
    {
        return self::nomeArquivoDrivePara((string) $aula->titulo, $tipo, $extensao);
    }

    /**
     * @return array{tipo: 'video'|'capa', titulo: string, extensao: string}|null
     */
    public static function interpretarArquivoDaPasta(string $nome): ?array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return null;
        }

        if (preg_match('/^(.+)_capa\.(jpe?g|png|webp)$/i', $nome, $m) === 1) {
            $ext = strtolower($m[2]);
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }

            return ['tipo' => 'capa', 'titulo' => $m[1], 'extensao' => $ext];
        }

        if (preg_match('/^(.+)\.mp4$/i', $nome, $m) === 1) {
            return ['tipo' => 'video', 'titulo' => $m[1], 'extensao' => 'mp4'];
        }

        return null;
    }
}
