<?php

namespace App\Http\Controllers\Api;

use App\Actions\ExcluirDisciplina;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDisciplinaRequest;
use App\Http\Requests\UpdateDisciplinaRequest;
use App\Http\Resources\DisciplinaResource;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DisciplinaController extends Controller
{
    use ApiResponse;

    public function index(Turma $turma): JsonResponse
    {
        $this->authorize('view', $turma);

        $disciplinas = $turma->disciplinas()
            ->withCount('aulas')
            ->ordenadasPorNome()
            ->get();

        return $this->ok(DisciplinaResource::collection($disciplinas)->resolve());
    }

    public function store(StoreDisciplinaRequest $request, Turma $turma): JsonResponse
    {
        $disciplina = $turma->disciplinas()->create($request->validated());

        return $this->ok(DisciplinaResource::make($disciplina)->resolve(), 'Disciplina criada', 201);
    }

    public function show(Disciplina $disciplina): JsonResponse
    {
        $this->authorize('view', $disciplina);

        $disciplina->load([
            'turma.curso',
            'aulas' => fn ($q) => $q->ordenadas(),
        ]);

        return $this->ok(DisciplinaResource::make($disciplina)->resolve());
    }

    public function update(UpdateDisciplinaRequest $request, Disciplina $disciplina): JsonResponse
    {
        $disciplina->update($request->validated());

        return $this->ok(DisciplinaResource::make($disciplina->fresh())->resolve(), 'Disciplina atualizada');
    }

    public function destroy(Disciplina $disciplina, ExcluirDisciplina $excluirDisciplina): JsonResponse
    {
        $this->authorize('delete', $disciplina);

        try {
            $excluirDisciplina->handle($disciplina);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409) {
                return $this->fail($e->getMessage(), [], 409);
            }

            throw $e;
        }

        return $this->ok(null, 'Disciplina removida');
    }
}
