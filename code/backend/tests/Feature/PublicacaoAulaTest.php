<?php

namespace Tests\Feature;

use App\Actions\PrepararVersaoDaAula;
use App\Models\Aula;
use App\Support\ValidarExportMp4;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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

    public function test_publicar_aula_pronta_libera_o_aluno_e_nao_muda_a_cobranca(): void
    {
        $this->comoCoordenacao();
        $aula = $this->gravarPlay(Aula::factory()->enviada()->create([
            'titulo' => 'Aula a publicar',
            'publicada' => false,
        ]));

        $this->getJson('/api/v1/resumo-mes')
            ->assertOk()
            ->assertJsonPath('data.publicadas', 0)
            ->assertJsonPath('data.valor_aulas_publicadas', 3.8);

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

    public function test_despublicar_tira_do_aluno_e_mantem_a_cobranca(): void
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
            ->assertJsonPath('data.valor_aulas_publicadas', 3.8);

        $this->postJson("/api/v1/aulas/{$aula->id}/despublicar")->assertOk();
        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'publicada' => false]);
        $this->assertNotNull($aula->fresh()->publicada_em);
    }

    public function test_preparar_publica_aula_nova_automaticamente(): void
    {
        $aula = Aula::factory()->preparando()->create([
            'titulo' => 'Primeiro envio',
            'chave_arquivo' => 'origens/primeiro.mp4',
        ]);
        Storage::disk((string) config('biblioteca.disk_aulas'))
            ->put($aula->chave_arquivo, ValidarExportMp4::amostraValida());

        app(PrepararVersaoDaAula::class)->handle($aula);

        $aula = $aula->fresh();
        $this->assertSame('pronta', $aula->status_preparo);
        $this->assertTrue($aula->publicada);
        $this->assertNotNull($aula->publicada_em);
    }

    public function test_apos_despublicar_preparar_de_novo_nao_republica(): void
    {
        $this->comoCoordenacao();
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Foi despublicada']));
        $publicadaEm = $aula->publicada_em;

        $this->postJson("/api/v1/aulas/{$aula->id}/despublicar")->assertOk();
        $this->assertFalse($aula->fresh()->publicada);

        $novaChave = 'origens/substituicao.mp4';
        Storage::disk((string) config('biblioteca.disk_aulas'))
            ->put($novaChave, ValidarExportMp4::amostraValida());
        $aula->update([
            'status_preparo' => 'preparando',
            'chave_arquivo' => $novaChave,
            'chave_play' => null,
        ]);

        app(PrepararVersaoDaAula::class)->handle($aula->fresh());

        $aula = $aula->fresh();
        $this->assertSame('pronta', $aula->status_preparo);
        $this->assertFalse($aula->publicada);
        $this->assertNotNull($aula->publicada_em);
        $this->assertTrue($publicadaEm->equalTo($aula->publicada_em));
    }
}
