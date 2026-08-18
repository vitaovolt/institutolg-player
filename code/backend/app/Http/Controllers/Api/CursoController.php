<?php

namespace App\Http\Controllers\Api;

use App\Actions\ExcluirCurso;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCursoRequest;
use App\Http\Requests\UpdateCursoRequest;
use App\Http\Resources\CursoResource;
use App\Models\Curso;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CursoController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Curso::class);

        $cursos = Curso::query()
            ->withCount('turmas')
            ->ordenadosPorNome()
            ->get();

        return $this->ok(CursoResource::collection($cursos)->resolve());
    }

    public function store(StoreCursoRequest $request): JsonResponse
    {
        $curso = Curso::query()->create($request->validated());

        return $this->ok(CursoResource::make($curso)->resolve(), 'Curso criado', 201);
    }

    public function show(Curso $curso): JsonResponse
    {
        $this->authorize('view', $curso);

        $curso->load(['turmas' => fn ($q) => $q->ordenadasPorNome()]);

        return $this->ok(CursoResource::make($curso)->resolve());
    }

    public function update(UpdateCursoRequest $request, Curso $curso): JsonResponse
    {
        $curso->update($request->validated());

        return $this->ok(CursoResource::make($curso->fresh())->resolve(), 'Curso atualizado');
    }

    public function destroy(Curso $curso, ExcluirCurso $excluirCurso): JsonResponse
    {
        $this->authorize('delete', $curso);

        try {
            $excluirCurso->handle($curso);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409) {
                return $this->fail($e->getMessage(), [], 409);
            }

            throw $e;
        }

        return $this->ok(null, 'Curso removido');
    }
}
