<?php

namespace Tests\Feature;

use App\Models\Aula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PlayerPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDiscosDaBiblioteca();
    }

    public function test_aluno_assiste_aula_publicada_com_url_temporaria_sem_download(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Introdução']));

        $pagina = $this->get('/assistir/'.$aula->token_publico);

        $pagina->assertOk()
            ->assertSee('controlslist="nodownload noplaybackrate', false)
            ->assertSee('data-testid="player-video"', false)
            ->assertSee('data-testid="player-speeds"', false)
            ->assertSee('data-testid="player-speed-4"', false)
            ->assertDontSee('download=', false)
            ->assertHeaderMissing('X-Frame-Options');
        $csp = (string) $pagina->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('frame-ancestors *', $csp);
        $this->assertStringContainsString("media-src 'self'", $csp);

        $src = [];
        preg_match('/data-testid="player-video"[^>]*src="([^"]+)"/', $pagina->getContent(), $src);
        $this->assertNotEmpty($src[1] ?? null);
        $urlMidia = html_entity_decode($src[1], ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('/assistir/'.$aula->token_publico.'/midia', $urlMidia);
        $this->assertStringContainsString('signature=', $urlMidia);

        $midia = $this->get($urlMidia);
        $midia->assertOk()
            ->assertHeader('Content-Type', 'video/mp4');
        $this->assertStringContainsString('inline', (string) $midia->headers->get('Content-Disposition'));
    }

    public function test_player_oferece_velocidade_1x_ate_4x_com_script_nonce(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Velocidade']));

        $pagina = $this->get('/assistir/'.$aula->token_publico);

        $pagina->assertOk()
            ->assertSee('data-testid="player-wrap"', false)
            ->assertSee('data-testid="player-speed-1"', false)
            ->assertSee('.player-wrap:hover .speeds', false)
            ->assertSee('bottom: 96px', false)
            ->assertSee('data-testid="player-speed-1-5"', false)
            ->assertSee('data-testid="player-speed-2"', false)
            ->assertSee('data-testid="player-speed-4"', false)
            ->assertSee('>1,5x<', false)
            ->assertSee('>4x<', false)
            ->assertSee('controlslist="nodownload noplaybackrate', false);

        $csp = (string) $pagina->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/", $csp);
        preg_match("/script-src 'self' 'nonce-([^']+)'/", $csp, $nonce);
        $this->assertNotEmpty($nonce[1] ?? null);
        $pagina->assertSee('nonce="'.$nonce[1].'"', false);
        $pagina->assertSee('video.playbackRate', false);
    }

    public function test_player_retoma_progresso_por_aula_no_local_storage(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Retomar']));

        $pagina = $this->get('/assistir/'.$aula->token_publico);

        $pagina->assertOk()
            ->assertSee('ilg-player-progresso', false)
            ->assertSee('restaurarProgresso', false)
            ->assertSee('salvarProgresso', false)
            ->assertSee($aula->token_publico, false);
    }

    public function test_aula_despublicada_nao_abre_o_player(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->enviada()->create([
            'titulo' => 'Só enviada',
            'publicada' => false,
        ]));

        $this->get('/assistir/'.$aula->token_publico)
            ->assertNotFound()
            ->assertSee('Esta aula não está disponível.');

        $url = URL::temporarySignedRoute(
            'player.midia',
            now()->addMinutes(30),
            ['aula' => $aula->token_publico],
            absolute: false,
        );

        $this->get($url)->assertNotFound();
    }

    public function test_midia_sem_assinatura_retorna_403(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create());

        $this->get('/assistir/'.$aula->token_publico.'/midia')->assertForbidden();
    }

    public function test_midia_com_assinatura_expirada_retorna_403(): void
    {
        Carbon::setTestNow('2026-08-18 12:00:00');
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create());
        $url = URL::temporarySignedRoute(
            'player.midia',
            now()->addMinutes(15),
            ['aula' => $aula->token_publico],
            absolute: false,
        );

        $this->get($url)->assertOk();

        Carbon::setTestNow('2026-08-18 12:20:00');
        $this->get($url)->assertForbidden();
    }

    public function test_mock_eduq_embute_iframe_do_player(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Casos clínicos']));

        $this->get('/eduq/'.$aula->token_publico)
            ->assertOk()
            ->assertSee('data-testid="mock-eduq"', false)
            ->assertSee('data-testid="iframe-player"', false)
            ->assertSee('/assistir/'.$aula->token_publico, false)
            ->assertSee('bloco Vídeo → Iframe');
    }
}
