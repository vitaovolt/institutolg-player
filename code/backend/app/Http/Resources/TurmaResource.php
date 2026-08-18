<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Turma */
class TurmaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'curso_id' => $this->curso_id,
            'nome' => $this->nome,
            'curso' => new CursoResource($this->whenLoaded('curso')),
            'disciplinas' => DisciplinaResource::collection($this->whenLoaded('disciplinas')),
        ];
    }
}
