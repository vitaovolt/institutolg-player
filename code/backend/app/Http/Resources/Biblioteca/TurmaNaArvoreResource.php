<?php

namespace App\Http\Resources\Biblioteca;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Turma */
class TurmaNaArvoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'disciplinas' => DisciplinaNaArvoreResource::collection($this->whenLoaded('disciplinas')),
        ];
    }
}
