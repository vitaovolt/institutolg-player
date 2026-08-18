<?php

namespace App\Support;

class ModoEnvioArquivo
{
    public static function usaMultipart(): bool
    {
        $disco = (string) config('biblioteca.disk_aulas', 'aulas');

        return config('filesystems.disks.'.$disco.'.driver') === 's3';
    }

    public static function tamanhoParte(): int
    {
        return max(5 * 1024 * 1024, (int) config('biblioteca.upload_part_bytes'));
    }
}
