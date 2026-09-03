<?php

namespace App\Http\Resources\Biblioteca;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Curso */
class CursoNaArvoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'turmas' => TurmaNaArvoreResource::collection($this->whenLoaded('turmas')),
        ];
    }
}
