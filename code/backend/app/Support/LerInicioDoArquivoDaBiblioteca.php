<?php

namespace App\Support;

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LerInicioDoArquivoDaBiblioteca
{
    public static function bytes(string $chave, int $n = 32): string
    {
        $n = max(12, $n);
        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));

        if ($disk instanceof AwsS3V3Adapter) {
            return self::viaRange($disk, $chave, $n);
        }

        return self::viaStream($disk, $chave, $n);
    }

    private static function viaRange(AwsS3V3Adapter $disk, string $chave, int $n): string
    {
        $cfg = $disk->getConfig();
        $prefix = trim((string) ($cfg['root'] ?? $cfg['prefix'] ?? ''), '/');
        $key = $prefix === '' ? $chave : $prefix.'/'.ltrim($chave, '/');

        $resultado = $disk->getClient()->getObject([
            'Bucket' => (string) ($cfg['bucket'] ?? ''),
            'Key' => $key,
            'Range' => 'bytes=0-'.($n - 1),
            '@http' => [
                'timeout' => 15,
                'connect_timeout' => 5,
            ],
        ]);

        $body = (string) $resultado->get('Body');
        if ($body === '') {
            throw new RuntimeException('Não foi possível ler o início do arquivo.');
        }

        return $body;
    }

    private static function viaStream(mixed $disk, string $chave, int $n): string
    {
        $stream = $disk->readStream($chave);
        if ($stream === false || $stream === null) {
            throw new RuntimeException('Não foi possível ler o início do arquivo.');
        }

        try {
            $header = (string) fread($stream, $n);

            return $header;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
