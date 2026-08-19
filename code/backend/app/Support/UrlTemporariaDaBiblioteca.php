<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class UrlTemporariaDaBiblioteca
{
    public static function paraChave(?string $chave): ?string
    {
        if (! filled($chave) || ! self::discoEhObjeto()) {
            return null;
        }

        $ttl = max(15, (int) config('biblioteca.play_ttl_minutos', 360));

        return Storage::disk((string) config('biblioteca.disk_aulas'))
            ->temporaryUrl($chave, now()->addMinutes($ttl));
    }

    public static function discoEhObjeto(): bool
    {
        $nome = (string) config('biblioteca.disk_aulas');

        return config("filesystems.disks.{$nome}.driver") === 's3';
    }

    public static function mimeDaChave(string $chave): string
    {
        return match (strtolower((string) pathinfo($chave, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
