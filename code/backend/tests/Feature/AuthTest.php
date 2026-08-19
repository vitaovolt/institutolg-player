<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CoordenacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_cria_carolina(): void
    {
        $this->seed(CoordenacaoSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'carolina@institutolg.local',
            'name' => 'Carolina',
        ]);
        $this->assertLessThanOrEqual(config('biblioteca.max_logins_painel'), User::query()->count());
    }

    public function test_login_retorna_token_e_grava_personal_access_token(): void
    {
        $user = User::factory()->create([
            'email' => 'carolina@institutolg.local',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'Carolina@InstitutoLG.local',
            'password' => 'password',
            'device_name' => 'test',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'carolina@institutolg.local')
            ->assertJsonPath('data.user.name', $user->name);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'test',
        ]);
    }

    public function test_login_invalido_nao_cria_token(): void
    {
        User::factory()->create([
            'email' => 'carolina@institutolg.local',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'carolina@institutolg.local',
            'password' => 'errada',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_health_continua_publico(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.checks.database', 'ok');
    }

    public function test_me_e_rotas_privadas_exigem_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh')->assertUnauthorized();
        $this->getJson('/api/v1/biblioteca')->assertUnauthorized();
        $this->getJson('/api/v1/resumo-mes')->assertUnauthorized();
        $this->getJson('/api/v1/cursos')->assertUnauthorized();
        $this->getJson('/api/v1/usuarios')->assertUnauthorized();
    }

    public function test_biblioteca_sem_accept_json_retorna_401_nao_500(): void
    {
        $this->withHeaders(['Accept' => '*/*'])
            ->get('/api/v1/biblioteca')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_me_logout_invalida_o_token(): void
    {
        $user = User::factory()->create([
            'email' => 'carolina@institutolg.local',
            'password' => 'password',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_refresh_rotaciona_token(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $antigo = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');

        $novo = $this->withToken($antigo)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->json('data.token');

        $this->assertNotSame($antigo, $novo);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->app['auth']->forgetGuards();

        $this->withToken($antigo)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $this->withToken($novo)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }
}
