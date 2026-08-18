<?php

namespace App\Contracts;

use App\Models\Aula;

interface AssinadorDeUploadDireto
{
    public function iniciar(Aula $aula): string;

    public function urlDaParte(Aula $aula, int $parte): string;

    /**
     * @param  list<array{part_number: int, etag: string}>  $partes
     */
    public function completar(Aula $aula, array $partes): void;
}
