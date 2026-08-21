<?php

namespace App\Http\Controllers\Api;

use App\Actions\MontarCustoArmazenamento;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustoArmazenamentoController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, MontarCustoArmazenamento $montar): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->fail('Não autenticado.', [], 401);
        }

        if (! $user->podeVerOpsArmazenamento()) {
            return $this->fail('Sem permissão.', [], 403);
        }

        return $this->ok($montar->handle());
    }
}
