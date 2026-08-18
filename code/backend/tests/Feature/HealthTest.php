<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_retorna_envelope_ok_com_check_de_database(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', 'institutolg-player-api')
            ->assertJsonPath('data.version', 'v1')
            ->assertJsonPath('data.checks.database', 'ok')
            ->assertJsonPath('message', 'API operacional')
            ->assertJsonPath('errors', [])
            ->assertJsonStructure([
                'success',
                'data' => ['service', 'version', 'status', 'checks'],
                'message',
                'errors',
            ]);

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_health_retorna_503_se_tabela_base_sumir(): void
    {
        Schema::drop('users');

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.status', 'degraded')
            ->assertJsonPath('data.checks.database', 'fail');
    }

    public function test_cors_permite_origem_do_spa(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/health')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
