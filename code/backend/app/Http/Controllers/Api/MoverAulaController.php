<?php

namespace App\Http\Controllers\Api;

use App\Actions\MoverAula;
use App\Http\Controllers\Controller;
use App\Http\Requests\MoverAulaRequest;
use App\Http\Resources\AulaResource;
use App\Models\Aula;
use App\Models\Disciplina;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MoverAulaController extends Controller
{
    use ApiResponse;

    public function __invoke(MoverAulaRequest $request, Aula $aula, MoverAula $mover): JsonResponse
    {
        $destino = Disciplina::query()->findOrFail($request->integer('disciplina_id'));

        try {
            $aula = $mover->handle($aula, $destino);
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), [], $e->getStatusCode());
        }

        return $this->ok(AulaResource::make($aula)->resolve(), 'Aula movida');
    }
}
