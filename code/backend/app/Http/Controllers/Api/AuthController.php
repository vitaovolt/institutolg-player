<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        if (! $user->podeGerirAcervo()) {
            return $this->fail('Esta conta não pode entrar no painel.', [], 403);
        }

        $device = $request->validated('device_name') ?: 'spa';
        $token = $user->createToken($device)->plainTextToken;

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->toAuthArray(),
        ], 'Login realizado');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok($request->user()->toAuthArray(), 'Usuário autenticado');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return $this->ok(null, 'Logout realizado');
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $atual = $user->currentAccessToken();
        if ($atual && method_exists($atual, 'delete')) {
            $atual->delete();
        }

        $token = $user->createToken('refresh')->plainTextToken;

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->toAuthArray(),
        ], 'Token renovado');
    }
}
