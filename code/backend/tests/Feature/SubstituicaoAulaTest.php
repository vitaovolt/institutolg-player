<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Support\ValidarExportMp4;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubstituicaoAulaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDiscosDaBiblioteca();
    }

    public function test_substituir_sem_token_retorna_401(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create());

        $this->postJson("/api/v1/aulas/{$aula->id}/envios/substituir")->assertUnauthorized();
    }

    public function test_substituir_mantem_html_e_troca_o_arquivo(): void
    {
        $this->comoCoordenacao();
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Casos clínicos']));
        $token = $aula->token_publico;
        $html = $aula->htmlIframe();
        $playAntes = $aula->chave_play;
        $mp4 = ValidarExportMp4::amostraValida();

        $iniciar = $this->postJson("/api/v1/aulas/{$aula->id}/envios/substituir")
            ->assertOk()
            ->assertJsonPath('data.aula.token_publico', $token)
            ->assertJsonPath('data.aula.status_preparo', 'enviando')
            ->assertJsonPath('data.upload_method', 'PUT');

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'token_publico' => $token,
            'status_preparo' => 'enviando',
        ]);

        $uploadPath = $iniciar->json('data.upload_path');
        $this->assertNotEmpty($uploadPath);

        $this->call('PUT', '/api/v1/'.ltrim($uploadPath, '/'), server: [
            'CONTENT_TYPE' => 'video/mp4',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $mp4)->assertOk();

        $this->postJson("/api/v1/aulas/{$aula->id}/envios/concluir")
            ->assertOk()
            ->assertJsonPath('data.token_publico', $token);

        $aula = $aula->fresh();
        $this->assertSame($token, $aula->token_publico);
        $this->assertSame($html, $aula->htmlIframe());
        $this->assertSame('pronta', $aula->status_preparo);
        $this->assertNotSame($playAntes, $aula->chave_play);
        $this->assertNotEmpty($aula->chave_play);
    }

    public function test_substituir_mantem_id_da_copia_para_o_proximo_sync_trocar_o_arquivo(): void
    {
        $this->comoCoordenacao();
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create([
            'titulo' => 'Troca no play',
            'drive_file_id' => 'arquivo-na-pasta',
            'status_drive' => 'ok',
        ]));

        $this->postJson("/api/v1/aulas/{$aula->id}/envios/substituir")
            ->assertOk()
            ->assertJsonPath('data.aula.status_preparo', 'enviando');

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'drive_file_id' => 'arquivo-na-pasta',
            'status_drive' => 'pendente',
        ]);
    }

    public function test_rascunho_nao_substitui(): void
    {
        $this->comoCoordenacao();
        $aula = Aula::factory()->create(['titulo' => 'Rascunho '.Str::uuid()]);

        $this->postJson("/api/v1/aulas/{$aula->id}/envios/substituir")
            ->assertStatus(422);

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'status_preparo' => 'rascunho',
        ]);
    }

    public function test_substituir_envio_preso_abre_novo_token(): void
    {
        $this->comoCoordenacao();
        $this->mock(\App\Contracts\AssinadorDeUploadDireto::class, function ($mock): void {
            $mock->shouldReceive('abortar')->once();
        });

        $aula = Aula::factory()->create([
            'titulo' => 'Aula 36',
            'status_preparo' => 'enviando',
            'token_upload' => 'token-velho-'.Str::random(8),
            's3_upload_id' => 'up-preso',
            'chave_arquivo' => 'origem/36/video.mp4',
        ]);
        $tokenPublico = $aula->token_publico;
        $tokenUploadAntes = $aula->token_upload;

        $this->postJson("/api/v1/aulas/{$aula->id}/envios/substituir")
            ->assertOk()
            ->assertJsonPath('data.aula.status_preparo', 'enviando')
            ->assertJsonPath('data.aula.token_publico', $tokenPublico);

        $aula = $aula->fresh();
        $this->assertSame($tokenPublico, $aula->token_publico);
        $this->assertNotSame($tokenUploadAntes, $aula->token_upload);
        $this->assertNull($aula->s3_upload_id);
        $this->assertSame('enviando', $aula->status_preparo);
    }
}
