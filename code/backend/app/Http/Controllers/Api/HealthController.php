<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->databaseOk();

        $payload = [
            'success' => $database,
            'data' => [
                'service' => 'institutolg-player-api',
                'version' => 'v1',
                'status' => $database ? 'ok' : 'degraded',
                'checks' => [
                    'database' => $database ? 'ok' : 'fail',
                ],
            ],
            'message' => $database ? 'API operacional' : 'API com falha no banco',
            'errors' => $database ? [] : ['database'],
        ];

        return response()->json($payload, $database ? 200 : 503);
    }

    private function databaseOk(): bool
    {
        try {
            DB::connection()->getPdo();

            return Schema::hasTable('users');
        } catch (\Throwable) {
            return false;
        }
    }
}
