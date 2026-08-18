<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CursoResource;
use App\Models\Curso;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BibliotecaController extends Controller
{
    use ApiResponse;

    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', Curso::class);

        $cursos = Curso::query()
            ->with([
                'turmas' => fn ($q) => $q->ordenadasPorNome(),
                'turmas.disciplinas' => fn ($q) => $q->ordenadasPorNome(),
                'turmas.disciplinas.aulas' => fn ($q) => $q->ordenadas(),
            ])
            ->ordenadosPorNome()
            ->get();

        return $this->ok(CursoResource::collection($cursos)->resolve());
    }
}
