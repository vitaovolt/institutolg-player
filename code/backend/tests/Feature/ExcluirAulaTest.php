<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Support\ValidarExportMp4;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExcluirAulaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDiscosDaBiblioteca();
    }

    public function test_excluir_sem_token_retorna_401(): void
    {
        $aula = Aula::factory()->create(['titulo' => 'Permanece']);

        $this->deleteJson("/api/v1/aulas/{$aula->id}")->assertUnauthorized();

        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'titulo' => 'Permanece']);
    }

    public function test_excluir_apaga_play_e_capa_e_nao_toca_a_pasta_compartilhada(): void
    {
        $this->comoCoordenacao();
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Para excluir']));

        $play = $aula->chave_play;
        $arquivo = 'origem/'.$aula->id.'/video.mp4';
        $capa = 'capas/'.$aula->id.'.png';
        $copiaPasta = 'pasta-compartilhada/nao-apagar.mp4';

        $aulas = Storage::disk((string) config('biblioteca.disk_aulas'));
        $drive = Storage::disk((string) config('biblioteca.disk_drive'));

        $aulas->put($arquivo, ValidarExportMp4::amostraValida());
        $aulas->put($capa, 'png-bytes');
        $drive->put($copiaPasta, 'copia-da-pasta');

        $aula->update([
            'chave_arquivo' => $arquivo,
            'chave_capa' => $capa,
        ]);

        $this->deleteJson("/api/v1/aulas/{$aula->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('aulas', ['id' => $aula->id]);
        $aulas->assertMissing($play);
        $aulas->assertMissing($arquivo);
        $aulas->assertMissing($capa);
        $drive->assertExists($copiaPasta);
    }
}
