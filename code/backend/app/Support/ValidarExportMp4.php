<?php

namespace App\Support;

class ValidarExportMp4
{
    public static function amostraValida(): string
    {
        $ftyp = pack('N', 24).'ftypisom'.pack('N', 0).'isomavc1';
        $mdat = pack('N', 8).'mdat';

        return $ftyp.$mdat;
    }

    public static function amostraMov(): string
    {
        return pack('N', 20).'ftypqt  '.pack('N', 0);
    }

    public static function pareceMp4(string $bytes): bool
    {
        if (strlen($bytes) < 12) {
            return false;
        }

        if (substr($bytes, 4, 4) !== 'ftyp') {
            return false;
        }

        $marca = substr($bytes, 8, 4);

        return $marca !== 'qt  ';
    }

    public static function mensagemRecusa(): string
    {
        return 'Tipo de arquivo não permitido, são permitidos somente arquivos MP4. Envie o export MP4 da aula pronta já editada.';
    }

    public static function mensagemGrandeDemais(): string
    {
        $maxGb = max(1, (int) round(config('biblioteca.upload_max_bytes') / (1024 * 1024 * 1024)));

        return "O arquivo é grande demais. Envie o export MP4 da aula pronta (máximo {$maxGb} GB).";
    }
}
