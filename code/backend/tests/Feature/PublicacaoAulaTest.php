<?php

namespace Tests\Feature;

use App\Models\Aula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicacaoAulaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDiscosDaBiblioteca();
        Carbon::setTestNow('2026-08-18 12:00:00');
    }

    public function test_publicar_sem_token_retorna_401(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->enviada()->create());

        $this->postJson("/api/v1/aulas/{$aula->id}/publicar")->assertUnauthorized();
        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'publicada' => false]);
    }

    public function test_publicar_aula_pronta_entra_na_cobranca(): void
    {
        $this->comoCoordenacao();
        $aula = $this->gravarPlay(Aula::factory()->enviada()->create([
            'titulo' => 'Aula a publicar',
            'publicada' => false,
        ]));

        $this->postJson("/api/v1/aulas/{$aula->id}/publicar")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.publicada', true)
            ->assertJsonPath('data.html_iframe', $aula->htmlIframe());

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'publicada' => true,
        ]);
        $this->assertNotNull($aula->fresh()->publicada_em);

        $this->getJson('/api/v1/resumo-mes')
            ->assertOk()
            ->assertJsonPath('data.publicadas', 1)
            ->assertJsonPath('data.valor_aulas_publicadas', 3.8);
    }

    public function test_nao_publica_aula_que_nao_esta_pronta(): void
    {
        $this->comoCoordenacao();
        $aula = Aula::factory()->create(['titulo' => 'Rascunho']);

        $this->postJson("/api/v1/aulas/{$aula->id}/publicar")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'publicada' => false]);
    }

    public function test_despublicar_tira_da_cobranca_e_e_idempotente(): void
    {
        $this->comoCoordenacao();
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Publicada']));

        $this->postJson("/api/v1/aulas/{$aula->id}/despublicar")
            ->assertOk()
            ->assertJsonPath('data.publicada', false);

        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'publicada' => false]);

        $this->getJson('/api/v1/resumo-mes')
            ->assertOk()
            ->assertJsonPath('data.publicadas', 0)
            ->assertJsonPath('data.valor_aulas_publicadas', 0);

        $this->postJson("/api/v1/aulas/{$aula->id}/despublicar")->assertOk();
        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'publicada' => false]);
    }
}
