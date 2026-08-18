<?php

namespace App\Actions;

use App\Models\Aula;
use Illuminate\Support\Facades\Storage;

class RemoverCapaDaAula
{
    public function handle(Aula $aula): Aula
    {
        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));

        if ($aula->chave_capa && $disk->exists($aula->chave_capa)) {
            $disk->delete($aula->chave_capa);
        }

        $aula->update(['chave_capa' => null]);

        return $aula->fresh();
    }
}
