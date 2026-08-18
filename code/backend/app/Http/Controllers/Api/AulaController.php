<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAulaRequest;
use App\Http\Requests\UpdateAulaRequest;
use App\Http\Resources\AulaResource;
use App\Models\Aula;
use App\Models\Disciplina;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AulaController extends Controller
{
    use ApiResponse;

    public function index(Disciplina $disciplina): JsonResponse
    {
        $this->authorize('view', $disciplina);

        $aulas = $disciplina->aulas()->ordenadas()->get();

        return $this->ok(AulaResource::collection($aulas)->resolve());
    }

    public function store(StoreAulaRequest $request, Disciplina $disciplina): JsonResponse
    {
        $aula = $disciplina->aulas()->create($request->validated());

        return $this->ok(AulaResource::make($aula)->resolve(), 'Aula criada', 201);
    }

    public function show(Aula $aula): JsonResponse
    {
        $this->authorize('view', $aula);

        $aula->load(['disciplina.turma.curso']);

        return $this->ok(AulaResource::make($aula)->resolve());
    }

    public function update(UpdateAulaRequest $request, Aula $aula): JsonResponse
    {
        $aula->update($request->validated());

        return $this->ok(AulaResource::make($aula->fresh())->resolve(), 'Aula atualizada');
    }

    public function destroy(Aula $aula): JsonResponse
    {
        $this->authorize('delete', $aula);

        $aula->delete();

        return $this->ok(null, 'Aula removida');
    }
}
