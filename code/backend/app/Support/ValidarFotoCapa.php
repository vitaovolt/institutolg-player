<?php

namespace App\Support;

class ValidarFotoCapa
{
    public static function amostraJpeg(): string
    {
        return pack('H*', 'ffd8ffe000104a46494600010100000100010000ffdb004300060404050404060505060706070807090b10090a08080b0d0c0b0b0c0e12100e0e11111113151814121317120f1116141111ffc0001108000100010301110000ffc4001f0000010501010101010100000000000000000102030405060708090a0bffda00080001000100003f00fbffd9');
    }

    public static function amostraPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==') ?: '';
    }

    public static function pareceImagem(string $bytes): bool
    {
        return self::mime($bytes) !== null;
    }

    public static function mime(string $bytes): ?string
    {
        if (strlen($bytes) < 12) {
            return null;
        }

        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }

        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }

    public static function extensao(string $bytes): string
    {
        return match (self::mime($bytes)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    public static function mensagemRecusa(): string
    {
        return 'Tipo de arquivo não permitido. Envie uma foto JPG ou PNG para a capa da aula.';
    }

    public static function mensagemGrandeDemais(): string
    {
        return 'A foto é grande demais. Envie um JPG ou PNG de até 2 MB.';
    }
}
