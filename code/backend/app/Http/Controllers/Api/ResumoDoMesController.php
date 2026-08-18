<?php

namespace App\Http\Controllers\Api;

use App\Actions\MontarResumoDoMes;
use App\Http\Controllers\Controller;
use App\Models\Aula;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ResumoDoMesController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, MontarResumoDoMes $montar): JsonResponse
    {
        $this->authorize('viewAny', Aula::class);

        $mes = $request->query('mes');
        $referencia = is_string($mes) && preg_match('/^\d{4}-\d{2}$/', $mes)
            ? Carbon::createFromFormat('Y-m', $mes)->startOfMonth()
            : null;

        return $this->ok($montar->handle($referencia));
    }
}
