<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;

class MoverArquivoDaBiblioteca
{
    public static function seExistir(Filesystem $disk, ?string $de, ?string $para): void
    {
        if (! filled($de) || ! filled($para) || $de === $para) {
            return;
        }

        if (! $disk->exists($de)) {
            return;
        }

        if ($disk->exists($para)) {
            $disk->delete($para);
        }

        $disk->move($de, $para);
    }
}
