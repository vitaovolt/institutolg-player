<?php

namespace App\Actions;

use App\Models\Aula;
use App\Support\CaminhoDaBiblioteca;
use App\Support\ValidarFotoCapa;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalvarCapaDaAula
{
    public function handle(Aula $aula, string $binario): Aula
    {
        if (strlen($binario) > (int) config('biblioteca.capa_max_bytes')) {
            throw new HttpException(422, ValidarFotoCapa::mensagemGrandeDemais());
        }

        if (! ValidarFotoCapa::pareceImagem($binario)) {
            throw new HttpException(422, ValidarFotoCapa::mensagemRecusa());
        }

        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));
        $anterior = $aula->chave_capa;
        $path = CaminhoDaBiblioteca::chaveCapa($aula, ValidarFotoCapa::extensao($binario));
        $disk->put($path, $binario);

        $aula->update(['chave_capa' => $path]);

        if ($anterior && $anterior !== $path && $disk->exists($anterior)) {
            $disk->delete($anterior);
        }

        return $aula->fresh();
    }
}
