<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_envia_headers_de_seguranca_e_csp(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_player_publico_nao_manda_x_frame_options_deny(): void
    {
        $this->fakeDiscosDaBiblioteca();
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Embed F5']));

        $pagina = $this->get('/assistir/'.$aula->token_publico);

        $pagina->assertOk();
        $this->assertNotSame('DENY', (string) $pagina->headers->get('X-Frame-Options'));
        $csp = (string) $pagina->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('frame-ancestors *', $csp);
        $this->assertStringContainsString("media-src 'self'", $csp);
        $this->assertSame('cross-origin', $pagina->headers->get('Cross-Origin-Resource-Policy'));
    }

    public function test_player_csp_libera_host_do_objeto_de_play(): void
    {
        $this->fakeDiscosDaBiblioteca();
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'CSP play']));

        config([
            'filesystems.disks.aulas.endpoint' => 'https://objetos.exemplo.test',
            'filesystems.disks.aulas.url' => 'https://objetos.exemplo.test',
        ]);

        $pagina = $this->get('/assistir/'.$aula->token_publico);

        $pagina->assertOk();
        $csp = (string) $pagina->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("media-src 'self' https://objetos.exemplo.test", $csp);
        $this->assertStringContainsString("img-src 'self' data: https://objetos.exemplo.test", $csp);
        $this->assertStringContainsString('frame-ancestors *', $csp);
    }

    public function test_cors_production_sem_frontend_url_nao_libera_origem(): void
    {
        $prevEnv = env('APP_ENV');
        $prevUrl = env('FRONTEND_URL');

        try {
            putenv('APP_ENV=production');
            putenv('FRONTEND_URL');
            $_ENV['APP_ENV'] = 'production';
            $_ENV['FRONTEND_URL'] = '';
            $_SERVER['APP_ENV'] = 'production';
            $_SERVER['FRONTEND_URL'] = '';

            $config = require base_path('config/cors.php');
            $this->assertSame([], $config['allowed_origins']);
        } finally {
            if ($prevEnv === false) {
                putenv('APP_ENV');
                unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
            } else {
                putenv('APP_ENV='.$prevEnv);
                $_ENV['APP_ENV'] = $prevEnv;
                $_SERVER['APP_ENV'] = $prevEnv;
            }
            if ($prevUrl === false || $prevUrl === null) {
                putenv('FRONTEND_URL');
                unset($_ENV['FRONTEND_URL'], $_SERVER['FRONTEND_URL']);
            } else {
                putenv('FRONTEND_URL='.$prevUrl);
                $_ENV['FRONTEND_URL'] = $prevUrl;
                $_SERVER['FRONTEND_URL'] = $prevUrl;
            }
        }
    }

    public function test_login_respeita_throttle_quando_limiter_apertado(): void
    {
        RateLimiter::for('login', fn () => Limit::perMinute(2)->by('hardening-test'));

        $payload = [
            'email' => 'naoexiste-hardening@local.test',
            'password' => 'errada',
        ];

        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(422);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(422);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
    }

    public function test_login_de_conta_inativa_nao_emite_token(): void
    {
        $user = User::factory()->inativo()->create([
            'email' => 'bloqueada@institutolg.local',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Esta conta não pode entrar no painel.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_json_da_aula_nao_vaza_caminho_de_arquivo(): void
    {
        $this->fakeDiscosDaBiblioteca();
        $this->comoCoordenacao();
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Sem path']));
        $aula->update(['chave_capa' => 'capas/'.$aula->id.'/secreta.png']);

        $response = $this->getJson('/api/v1/aulas/'.$aula->id)->assertOk();
        $json = $response->getContent();

        $this->assertStringNotContainsString('chave_arquivo', $json);
        $this->assertStringNotContainsString('chave_play', $json);
        $this->assertStringNotContainsString('chave_capa', $json);
        $this->assertStringNotContainsString('capas/'.$aula->id, $json);
        $this->assertStringNotContainsString('play/'.$aula->id, $json);
        $response->assertJsonPath('data.tem_capa', true);
        $response->assertJsonPath('data.tem_arquivo', false);
    }
}
