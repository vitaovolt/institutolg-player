<?php

namespace App\Actions;

use App\Contracts\AssinadorDeUploadDireto;
use App\Models\Aula;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ExcluirAula
{
    public function __construct(private AssinadorDeUploadDireto $assinador) {}

    public function handle(Aula $aula): void
    {
        Cache::lock('aula-envio:'.$aula->id, 20)->block(10, function () use ($aula): void {
            $aula->refresh();
            $this->assinador->abortar($aula);
            $this->apagarDoPlay($aula);
            $aula->delete();
        });
    }

    private function apagarDoPlay(Aula $aula): void
    {
        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));
        $chaves = array_unique(array_filter([
            $aula->chave_arquivo,
            $aula->chave_play,
            $aula->chave_capa,
        ], fn ($chave) => filled($chave)));

        foreach ($chaves as $chave) {
            if ($disk->exists($chave)) {
                $disk->delete($chave);
            }
        }
    }
}
