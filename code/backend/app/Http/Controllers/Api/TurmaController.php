<?php

namespace App\Http\Controllers\Api;

use App\Actions\ExcluirTurma;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTurmaRequest;
use App\Http\Requests\UpdateTurmaRequest;
use App\Http\Resources\TurmaResource;
use App\Models\Curso;
use App\Models\Turma;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TurmaController extends Controller
{
    use ApiResponse;

    public function index(Curso $curso): JsonResponse
    {
        $this->authorize('view', $curso);

        $turmas = $curso->turmas()
            ->withCount('disciplinas')
            ->ordenadasPorNome()
            ->get();

        return $this->ok(TurmaResource::collection($turmas)->resolve());
    }

    public function store(StoreTurmaRequest $request, Curso $curso): JsonResponse
    {
        $turma = $curso->turmas()->create($request->validated());

        return $this->ok(TurmaResource::make($turma)->resolve(), 'Turma criada', 201);
    }

    public function show(Turma $turma): JsonResponse
    {
        $this->authorize('view', $turma);

        $turma->load(['disciplinas' => fn ($q) => $q->ordenadasPorNome()]);

        return $this->ok(TurmaResource::make($turma)->resolve());
    }

    public function update(UpdateTurmaRequest $request, Turma $turma): JsonResponse
    {
        $turma->update($request->validated());

        return $this->ok(TurmaResource::make($turma->fresh())->resolve(), 'Turma atualizada');
    }

    public function destroy(Turma $turma, ExcluirTurma $excluirTurma): JsonResponse
    {
        $this->authorize('delete', $turma);

        try {
            $excluirTurma->handle($turma);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409) {
                return $this->fail($e->getMessage(), [], 409);
            }

            throw $e;
        }

        return $this->ok(null, 'Turma removida');
    }
}
