<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $usuarios = User::query()->orderBy('name')->orderBy('id')->get();

        return $this->ok(UsuarioResource::collection($usuarios)->resolve());
    }

    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $usuario = User::query()->create($request->validated());

        return $this->ok(UsuarioResource::make($usuario)->resolve(), 'Usuário criado', 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->ok(UsuarioResource::make($user)->resolve());
    }

    public function update(UpdateUsuarioRequest $request, User $user): JsonResponse
    {
        $dados = $request->validated();
        if (! filled($dados['password'] ?? null)) {
            unset($dados['password']);
        }

        $user->update($dados);

        if ($user->wasChanged('ativo') && $user->ativo === false) {
            $user->tokens()->delete();
        }

        return $this->ok(UsuarioResource::make($user->fresh())->resolve(), 'Usuário atualizado');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->is($request->user())) {
            return $this->fail('Você não pode excluir a própria conta.', [], 422);
        }

        if ($user->ativo && User::query()->where('ativo', true)->count() === 1) {
            return $this->fail('Não é possível remover a última conta ativa.', [], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->ok(null, 'Usuário removido');
    }
}
