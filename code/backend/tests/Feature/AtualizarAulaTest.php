<?php

namespace Tests\Feature;

use App\Jobs\CopiarAulaParaDriveJob;
use App\Models\Aula;
use App\Support\CaminhoDaBiblioteca;
use App\Support\ValidarExportMp4;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AtualizarAulaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDiscosDaBiblioteca();
    }

    public function test_atualizar_sem_token_retorna_401(): void
    {
        $aula = Aula::factory()->create(['titulo' => 'Permanece']);

        $this->putJson("/api/v1/aulas/{$aula->id}", ['titulo' => 'Outro nome'])
            ->assertUnauthorized();

        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'titulo' => 'Permanece']);
    }

    public function test_atualizar_titulo_nao_move_o_arquivo_do_play(): void
    {
        Queue::fake();
        $this->comoCoordenacao();
        $aula = $this->aulaComPlayECapa('Nome antigo');
        $disciplina = $aula->disciplina;
        $videoAntigo = CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome antigo');
        $capaAntiga = CaminhoDaBiblioteca::chaveCapaPara($disciplina, 'Nome antigo', 'png');
        $aulas = Storage::disk((string) config('biblioteca.disk_aulas'));

        $this->putJson("/api/v1/aulas/{$aula->id}", ['titulo' => 'Nome novo'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.titulo', 'Nome novo');

        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'titulo' => 'Nome novo']);
        $aulas->assertExists($videoAntigo);
        $aulas->assertExists($capaAntiga);
        $aulas->assertMissing(CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome novo'));
        $this->assertSame($videoAntigo, $aula->fresh()->chave_play);
        $this->assertSame($capaAntiga, $aula->fresh()->chave_capa);
        Queue::assertPushed(CopiarAulaParaDriveJob::class, fn (CopiarAulaParaDriveJob $job) => $job->aulaId === $aula->id);
    }

    public function test_aluno_ve_o_titulo_novo_no_player(): void
    {
        Queue::fake();
        $this->comoCoordenacao();
        $aula = $this->aulaComPlayECapa('bfb-oaxi-pcm EDITADO');

        $this->putJson("/api/v1/aulas/{$aula->id}", [
            'titulo' => 'D5 Doenças inflamatórias intestinais',
        ])->assertOk();

        $this->get('/assistir/'.$aula->fresh()->token_publico)
            ->assertOk()
            ->assertSee('D5 Doenças inflamatórias intestinais', false)
            ->assertDontSee('bfb-oaxi-pcm', false);
    }

    public function test_atualizar_titulo_nao_move_a_pasta_compartilhada_no_request(): void
    {
        Queue::fake();
        $this->comoCoordenacao();
        $aula = $this->aulaComPlayECapa('Nome antigo');
        $disciplina = $aula->disciplina;
        $drive = Storage::disk((string) config('biblioteca.disk_drive'));
        $drive->put(CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome antigo'), 'copia-video');
        $drive->put(CaminhoDaBiblioteca::chaveCapaPara($disciplina, 'Nome antigo', 'png'), 'copia-capa');

        $this->putJson("/api/v1/aulas/{$aula->id}", ['titulo' => 'Nome novo'])->assertOk();

        $drive->assertExists(CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome antigo'));
        $drive->assertExists(CaminhoDaBiblioteca::chaveCapaPara($disciplina, 'Nome antigo', 'png'));
        $drive->assertMissing(CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome novo'));
        Queue::assertPushed(CopiarAulaParaDriveJob::class);
    }

    public function test_editar_titulo_nao_chama_cliente_da_pasta_no_request(): void
    {
        Queue::fake();
        Http::fake();
        $this->mock(\App\Services\Integrations\ClientePastaDrive::class, function ($mock) {
            $mock->shouldNotReceive('enviarCopia');
            $mock->shouldNotReceive('renomearCopia');
            $mock->shouldNotReceive('sincronizarAula');
        });
        $this->comoCoordenacao();
        $aula = $this->aulaComPlayECapa('Nome antigo');

        $this->putJson("/api/v1/aulas/{$aula->id}", ['titulo' => 'Nome novo'])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Nome novo');

        Http::assertNothingSent();
        Queue::assertPushed(CopiarAulaParaDriveJob::class);
    }

    public function test_titulo_duplicado_na_mesma_disciplina_retorna_422(): void
    {
        $this->comoCoordenacao();
        $aula = Aula::factory()->create(['titulo' => 'Aula A']);
        Aula::factory()->create([
            'disciplina_id' => $aula->disciplina_id,
            'titulo' => 'Aula B',
        ]);

        $this->putJson("/api/v1/aulas/{$aula->id}", ['titulo' => 'Aula B'])
            ->assertStatus(422)
            ->assertJsonPath('errors.titulo.0', 'Já existe uma aula com este título nesta disciplina.');

        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'titulo' => 'Aula A']);
    }

    public function test_rascunho_nao_enfileira_copia_ao_renomear(): void
    {
        Queue::fake();
        $this->comoCoordenacao();
        $aula = Aula::factory()->create(['titulo' => 'Rascunho']);

        $this->putJson("/api/v1/aulas/{$aula->id}", ['titulo' => 'Rascunho novo'])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Rascunho novo');

        Queue::assertNotPushed(CopiarAulaParaDriveJob::class);
    }

    private function aulaComPlayECapa(string $titulo): Aula
    {
        $aula = Aula::factory()->publicada()->create(['titulo' => $titulo]);
        $aula->load('disciplina.turma.curso');
        $video = CaminhoDaBiblioteca::chaveVideo($aula->disciplina, $titulo);
        $capa = CaminhoDaBiblioteca::chaveCapaPara($aula->disciplina, $titulo, 'png');
        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));
        $disk->put($video, ValidarExportMp4::amostraValida());
        $disk->put($capa, 'png-bytes');
        $aula->update([
            'chave_arquivo' => $video,
            'chave_play' => $video,
            'chave_capa' => $capa,
            'status_preparo' => 'pronta',
        ]);

        return $aula->fresh(['disciplina.turma.curso']);
    }
}
