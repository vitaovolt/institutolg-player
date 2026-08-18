<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Disciplina */
class DisciplinaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'turma_id' => $this->turma_id,
            'nome' => $this->nome,
            'turma' => new TurmaResource($this->whenLoaded('turma')),
            'aulas' => AulaResource::collection($this->whenLoaded('aulas')),
        ];
    }
}
