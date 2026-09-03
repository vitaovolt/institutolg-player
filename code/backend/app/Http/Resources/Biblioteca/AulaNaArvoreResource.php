<?php

namespace App\Http\Resources\Biblioteca;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Aula */
class AulaNaArvoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'ordem' => $this->ordem,
            'status_preparo' => $this->status_preparo,
            'publicada' => $this->publicada,
            'tem_capa' => filled($this->chave_capa),
            'url_capa' => $this->urlCapa(),
        ];
    }
}
