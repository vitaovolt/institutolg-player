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

    public static function chaveCapa(Aula $aula, string $extensao): string
    {
        $ext = strtolower(ltrim($extensao, '.'));
        if ($ext === '') {
            $ext = 'jpg';
        }

        return self::prefixo($aula).'/'.self::segmento((string) $aula->titulo).'_capa.'.$ext;
    }

    public static function nomeArquivoDrive(Aula $aula, string $tipo = 'video', string $extensao = 'mp4'): string
    {
        $base = str_replace(['/', '\\'], '-', trim((string) $aula->titulo));
        if ($base === '') {
            $base = 'aula';
        }
        $ext = strtolower(ltrim($extensao, '.'));
        if ($tipo === 'capa') {
            return $base.'_capa.'.$ext;
        }

        return $base.'.mp4';
    }
}
