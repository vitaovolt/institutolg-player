<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function ok(mixed $data = null, string $message = 'Operação realizada com sucesso', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'errors' => [],
        ], $status);
    }

    protected function fail(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
