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

    /**
     * @return resource
     */
    public static function stream(string $chave)
    {
        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));

        if ($disk instanceof AwsS3V3Adapter) {
            return self::viaObjetoStream($disk, $chave);
        }

        $stream = $disk->readStream($chave);
        if ($stream === false || $stream === null) {
            throw new RuntimeException('Não foi possível ler o arquivo.');
        }

        return $stream;
    }

    private static function viaRange(AwsS3V3Adapter $disk, string $chave, int $n): string
    {
        $resultado = $disk->getClient()->getObject([
            'Bucket' => self::bucket($disk),
            'Key' => self::chaveNoBucket($disk, $chave),
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

    /**
     * @return resource
     */
    private static function viaObjetoStream(AwsS3V3Adapter $disk, string $chave)
    {
        $resultado = $disk->getClient()->getObject([
            'Bucket' => self::bucket($disk),
            'Key' => self::chaveNoBucket($disk, $chave),
            '@http' => [
                'stream' => true,
                'timeout' => 43200,
                'connect_timeout' => 15,
            ],
        ]);

        $body = $resultado->get('Body');
        $resource = is_object($body) && method_exists($body, 'detach') ? $body->detach() : null;
        if (! is_resource($resource)) {
            throw new RuntimeException('Não foi possível abrir o arquivo em fluxo.');
        }

        return $resource;
    }

    private static function viaStream(mixed $disk, string $chave, int $n): string
    {
        $stream = $disk->readStream($chave);
        if ($stream === false || $stream === null) {
            throw new RuntimeException('Não foi possível ler o início do arquivo.');
        }

        try {
            return (string) fread($stream, $n);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private static function bucket(AwsS3V3Adapter $disk): string
    {
        return (string) ($disk->getConfig()['bucket'] ?? '');
    }

    private static function chaveNoBucket(AwsS3V3Adapter $disk, string $chave): string
    {
        $cfg = $disk->getConfig();
        $prefix = trim((string) ($cfg['root'] ?? $cfg['prefix'] ?? ''), '/');

        return $prefix === '' ? $chave : $prefix.'/'.ltrim($chave, '/');
    }
}
