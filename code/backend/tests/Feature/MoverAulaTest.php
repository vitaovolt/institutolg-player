<?php

namespace Tests\Feature;

use App\Jobs\CopiarAulaParaDriveJob;
use App\Models\Aula;
use App\Models\Disciplina;
use App\Support\CaminhoDaBiblioteca;
use App\Support\ValidarExportMp4;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MoverAulaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDiscosDaBiblioteca();
    }

    public function test_mover_sem_token_retorna_401(): void
    {
        [$origem, $destino] = $this->duasDisciplinas();
        $aula = Aula::factory()->create([
            'disciplina_id' => $origem->id,
            'titulo' => 'Microbioma',
        ]);

        $this->postJson("/api/v1/aulas/{$aula->id}/mover", [
            'disciplina_id' => $destino->id,
        ])->assertUnauthorized();

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'disciplina_id' => $origem->id,
        ]);
    }

    public function test_conta_inativa_nao_move(): void
    {
        $this->comoInativo();
        [$origem, $destino] = $this->duasDisciplinas();
        $aula = Aula::factory()->create([
            'disciplina_id' => $origem->id,
            'titulo' => 'Microbioma',
        ]);

        $this->postJson("/api/v1/aulas/{$aula->id}/mover", [
            'disciplina_id' => $destino->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'disciplina_id' => $origem->id,
        ]);
    }

    public function test_mover_troca_disciplina_sem_recopiar_o_play(): void
    {
        Queue::fake();
        $this->comoCoordenacao();
        [$origem, $destino] = $this->duasDisciplinas();
        $aula = $this->aulaProntaNaDisciplina($origem, 'NO3. A4 Microbioma');
        $chavePlay = $aula->chave_play;
        $token = $aula->token_publico;
        $enviadoEm = $aula->enviado_em?->toIso8601String();

        $this->postJson("/api/v1/aulas/{$aula->id}/mover", [
            'disciplina_id' => $destino->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.disciplina_id', $destino->id)
            ->assertJsonPath('data.disciplina.id', $destino->id)
            ->assertJsonPath('data.disciplina.nome', 'A4')
            ->assertJsonPath('data.token_publico', $token);

        $aula = $aula->fresh();
        $this->assertSame($destino->id, $aula->disciplina_id);
        $this->assertSame($chavePlay, $aula->chave_play);
        $this->assertSame($token, $aula->token_publico);
        $this->assertSame($enviadoEm, $aula->enviado_em?->toIso8601String());
        $this->assertSame(1, $aula->ordem);
        Storage::disk((string) config('biblioteca.disk_aulas'))->assertExists($chavePlay);
        Queue::assertPushed(CopiarAulaParaDriveJob::class, fn (CopiarAulaParaDriveJob $job) => $job->aulaId === $aula->id);
    }

    public function test_mover_alinha_a_pasta_compartilhada_sem_mover_o_play(): void
    {
        $this->comoCoordenacao();
        [$origem, $destino] = $this->duasDisciplinas();
        $aula = $this->aulaProntaNaDisciplina($origem, 'NO3. A4 Microbioma');
        $chavePlay = $aula->chave_play;
        $caminhoAntigo = CaminhoDaBiblioteca::chaveVideo($origem, (string) $aula->titulo);
        $caminhoNovo = CaminhoDaBiblioteca::chaveVideo($destino, (string) $aula->titulo);

        (new CopiarAulaParaDriveJob($aula->id))->handle(app(\App\Services\Integrations\ClientePastaDrive::class));
        $drive = Storage::disk((string) config('biblioteca.disk_drive'));
        $drive->assertExists($caminhoAntigo);

        $this->postJson("/api/v1/aulas/{$aula->id}/mover", [
            'disciplina_id' => $destino->id,
        ])->assertOk();

        $drive->assertExists($caminhoNovo);
        $drive->assertMissing($caminhoAntigo);
        Storage::disk((string) config('biblioteca.disk_aulas'))->assertExists($chavePlay);
        $this->assertSame($chavePlay, $aula->fresh()->chave_play);
        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'disciplina_id' => $destino->id,
            'status_drive' => 'ok',
        ]);
    }

    public function test_aluno_continua_vendo_a_aula_pelo_mesmo_token(): void
    {
        Queue::fake();
        $this->comoCoordenacao();
        [$origem, $destino] = $this->duasDisciplinas();
        $aula = $this->aulaProntaNaDisciplina($origem, 'Microbioma');

        $this->postJson("/api/v1/aulas/{$aula->id}/mover", [
            'disciplina_id' => $destino->id,
        ])->assertOk();

        $this->get('/assistir/'.$aula->token_publico)
            ->assertOk()
            ->assertSee('Microbioma', false);
    }

    public function test_rascunho_nao_enfileira_copia_ao_mover(): void
    {
        Queue::fake();
        $this->comoCoordenacao();
        [$origem, $destino] = $this->duasDisciplinas();
        $aula = Aula::factory()->create([
            'disciplina_id' => $origem->id,
            'titulo' => 'Rascunho',
        ]);

        $this->postJson("/api/v1/aulas/{$aula->id}/mover", [
            'disciplina_id' => $destino->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.disciplina_id', $destino->id);

        Queue::assertNotPushed(CopiarAulaParaDriveJob::class);
    }

    public function test_mesma_disciplina_retorna_422(): void
    {
        $this->comoCoordenacao();
        [$origem] = $this->duasDisciplinas();
        $aula = Aula::factory()->create([
            'disciplina_id' => $origem->id,
            'titulo' => 'Microbioma',
        ]);

        $this->postJson("/api/v1/aulas/{$aula->id}/mover", [
            'disciplina_id' => $origem->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.disciplina_id.0', 'A aula já está nesta disciplina.');

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'disciplina_id' => $origem->id,
        ]);
    }

    public function test_titulo_duplicado_no_destino_retorna_422(): void
    {
        $this->comoCoordenacao();
        [$origem, $destino] = $this->duasDisciplinas();
        $aula = Aula::factory()->create([
            'disciplina_id' => $origem->id,
            'titulo' => 'Microbioma',
        ]);
        Aula::factory()->create([
            'disciplina_id' => $destino->id,
            'titulo' => 'Microbioma',
        ]);

        $this->postJson("/api/v1/aulas/{$aula->id}/mover", [
            'disciplina_id' => $destino->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.disciplina_id.0', 'Já existe uma aula com este título nesta disciplina.');

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'disciplina_id' => $origem->id,
        ]);
    }

    public function test_envio_em_andamento_nao_move(): void
    {
        $this->comoCoordenacao();
        [$origem, $destino] = $this->duasDisciplinas();
        $aula = Aula::factory()->create([
            'disciplina_id' => $origem->id,
            'titulo' => 'Microbioma',
            'status_preparo' => 'enviando',
        ]);

        $this->postJson("/api/v1/aulas/{$aula->id}/mover", [
            'disciplina_id' => $destino->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Espere o envio terminar para mover a aula.');

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'disciplina_id' => $origem->id,
        ]);
    }

    /**
     * @return array{0: Disciplina, 1: Disciplina}
     */
    private function duasDisciplinas(): array
    {
        $origem = Disciplina::factory()->create(['nome' => 'A3']);
        $destino = Disciplina::factory()->create([
            'turma_id' => $origem->turma_id,
            'nome' => 'A4',
        ]);

        return [$origem, $destino];
    }

    private function aulaProntaNaDisciplina(Disciplina $disciplina, string $titulo): Aula
    {
        $aula = Aula::factory()->publicada()->create([
            'disciplina_id' => $disciplina->id,
            'titulo' => $titulo,
            'ordem' => 1,
        ]);
        $video = CaminhoDaBiblioteca::chaveVideo($disciplina, $titulo);
        Storage::disk((string) config('biblioteca.disk_aulas'))->put($video, ValidarExportMp4::amostraValida());
        $aula->update([
            'chave_arquivo' => $video,
            'chave_play' => $video,
            'status_preparo' => 'pronta',
        ]);

        return $aula->fresh(['disciplina.turma.curso']);
    }
}
