<?php

namespace App\Http\Resources\Biblioteca;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Disciplina */
class DisciplinaNaArvoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'aulas' => AulaNaArvoreResource::collection($this->whenLoaded('aulas')),
        ];
    }
}
