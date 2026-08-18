<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Curso */
class CursoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'turmas_count' => $this->whenCounted('turmas'),
            'turmas' => TurmaResource::collection($this->whenLoaded('turmas')),
        ];
    }
}
