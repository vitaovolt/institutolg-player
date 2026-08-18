<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Support\ValidarExportMp4;
use App\Support\ValidarFotoCapa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CapaAulaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDiscosDaBiblioteca();
    }

    public function test_salvar_capa_sem_token_retorna_401(): void
    {
        $aula = Aula::factory()->create();
        $png = UploadedFile::fake()->createWithContent('capa.png', ValidarFotoCapa::amostraPng());

        $this->post("/api/v1/aulas/{$aula->id}/capa", ['capa' => $png], ['Accept' => 'application/json'])
            ->assertUnauthorized();
        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'chave_capa' => null]);
    }

    public function test_salva_capa_png_e_serve_no_player(): void
    {
        $this->comoCoordenacao();
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Com capa']));
        $png = UploadedFile::fake()->createWithContent('capa.png', ValidarFotoCapa::amostraPng());

        $this->post("/api/v1/aulas/{$aula->id}/capa", ['capa' => $png], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tem_capa', true);

        $aula = $aula->fresh();
        $this->assertNotEmpty($aula->chave_capa);
        Storage::disk((string) config('biblioteca.disk_aulas'))->assertExists($aula->chave_capa);

        $this->get('/capa/'.$aula->token_publico)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->get('/assistir/'.$aula->token_publico)
            ->assertOk()
            ->assertSee('poster="', false)
            ->assertSee('/capa/'.$aula->token_publico, false);
    }

    public function test_recusa_mp4_como_capa_e_nao_grava(): void
    {
        $this->comoCoordenacao();
        $aula = Aula::factory()->create();
        $mp4 = UploadedFile::fake()->createWithContent('capa.mp4', ValidarExportMp4::amostraValida());

        $this->post("/api/v1/aulas/{$aula->id}/capa", ['capa' => $mp4], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'chave_capa' => null]);
    }

    public function test_remover_capa_apaga_arquivo(): void
    {
        $this->comoCoordenacao();
        $aula = Aula::factory()->create();
        $png = UploadedFile::fake()->createWithContent('capa.png', ValidarFotoCapa::amostraPng());
        $this->post("/api/v1/aulas/{$aula->id}/capa", ['capa' => $png], ['Accept' => 'application/json'])->assertOk();
        $path = $aula->fresh()->chave_capa;

        $this->deleteJson("/api/v1/aulas/{$aula->id}/capa")
            ->assertOk()
            ->assertJsonPath('data.tem_capa', false);

        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'chave_capa' => null]);
        Storage::disk((string) config('biblioteca.disk_aulas'))->assertMissing($path);
        $this->get('/capa/'.$aula->token_publico)->assertNotFound();
    }
}
