<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Turma;
use Database\Seeders\BibliotecaPilotoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DominioApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comoCoordenacao();
    }

    public function test_seed_piloto_cria_arvore_e_tres_aulas(): void
    {
        $this->seed(BibliotecaPilotoSeeder::class);

        $this->assertDatabaseHas('cursos', ['nome' => 'Pós-graduação em Saúde']);
        $this->assertDatabaseHas('turmas', ['nome' => 'Turma 2026-A']);
        $this->assertDatabaseHas('disciplinas', ['nome' => 'Cardiologia']);
        $this->assertDatabaseHas('aulas', ['titulo' => 'Introdução', 'publicada' => true, 'status_preparo' => 'pronta']);
        $this->assertDatabaseHas('aulas', ['titulo' => 'Casos clínicos', 'publicada' => true, 'status_drive' => 'enviando']);
        $this->assertDatabaseHas('aulas', ['titulo' => 'Revisão', 'publicada' => false, 'status_preparo' => 'preparando']);
        $this->assertSame(3, Aula::query()->count());
    }

    public function test_biblioteca_devolve_arvore_com_relacoes(): void
    {
        $this->seed(BibliotecaPilotoSeeder::class);

        $response = $this->getJson('/api/v1/biblioteca');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.nome', 'Pós-graduação em Saúde')
            ->assertJsonPath('data.0.turmas.0.nome', 'Turma 2026-A')
            ->assertJsonPath('data.0.turmas.0.disciplinas.0.nome', 'Cardiologia')
            ->assertJsonPath('data.0.turmas.0.disciplinas.0.aulas.0.titulo', 'Introdução')
            ->assertJsonPath('data.0.turmas.0.disciplinas.0.aulas.2.titulo', 'Revisão');
    }

    public function test_crud_curso_turma_disciplina_aula_persiste(): void
    {
        $cursoRes = $this->postJson('/api/v1/cursos', ['nome' => 'Especialização em Enfermagem']);
        $cursoRes->assertCreated()->assertJsonPath('data.nome', 'Especialização em Enfermagem');
        $cursoId = $cursoRes->json('data.id');
        $this->assertDatabaseHas('cursos', ['id' => $cursoId, 'nome' => 'Especialização em Enfermagem']);

        $turmaRes = $this->postJson("/api/v1/cursos/{$cursoId}/turmas", ['nome' => 'Turma 2026-B']);
        $turmaRes->assertCreated();
        $turmaId = $turmaRes->json('data.id');
        $this->assertDatabaseHas('turmas', ['id' => $turmaId, 'curso_id' => $cursoId, 'nome' => 'Turma 2026-B']);

        $discRes = $this->postJson("/api/v1/turmas/{$turmaId}/disciplinas", ['nome' => 'Pediatria']);
        $discRes->assertCreated();
        $discId = $discRes->json('data.id');
        $this->assertDatabaseHas('disciplinas', ['id' => $discId, 'turma_id' => $turmaId, 'nome' => 'Pediatria']);

        $aulaRes = $this->postJson("/api/v1/disciplinas/{$discId}/aulas", ['titulo' => 'Semiologia']);
        $aulaRes->assertCreated()
            ->assertJsonPath('data.titulo', 'Semiologia')
            ->assertJsonPath('data.status_preparo', 'rascunho')
            ->assertJsonPath('data.publicada', false);
        $aulaId = $aulaRes->json('data.id');
        $this->assertDatabaseHas('aulas', [
            'id' => $aulaId,
            'disciplina_id' => $discId,
            'titulo' => 'Semiologia',
            'ordem' => 1,
        ]);
        $this->assertNotEmpty($aulaRes->json('data.token_publico'));

        $this->putJson("/api/v1/aulas/{$aulaId}", ['titulo' => 'Semiologia I', 'ordem' => 2])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Semiologia I');
        $this->assertDatabaseHas('aulas', ['id' => $aulaId, 'titulo' => 'Semiologia I', 'ordem' => 2]);

        $this->deleteJson("/api/v1/aulas/{$aulaId}")->assertOk();
        $this->assertDatabaseMissing('aulas', ['id' => $aulaId]);
    }

    public function test_mesmo_titulo_em_turmas_diferentes_sao_aulas_distintas(): void
    {
        $curso = Curso::factory()->create();
        $turmaA = Turma::factory()->create(['curso_id' => $curso->id, 'nome' => 'A']);
        $turmaB = Turma::factory()->create(['curso_id' => $curso->id, 'nome' => 'B']);
        $discA = Disciplina::factory()->create(['turma_id' => $turmaA->id, 'nome' => 'Cardio']);
        $discB = Disciplina::factory()->create(['turma_id' => $turmaB->id, 'nome' => 'Cardio']);

        $this->postJson("/api/v1/disciplinas/{$discA->id}/aulas", ['titulo' => 'Introdução'])->assertCreated();
        $this->postJson("/api/v1/disciplinas/{$discB->id}/aulas", ['titulo' => 'Introdução'])->assertCreated();

        $this->assertSame(2, Aula::query()->where('titulo', 'Introdução')->count());
    }

    public function test_nome_duplicado_no_mesmo_pai_retorna_422(): void
    {
        $curso = Curso::factory()->create(['nome' => 'Curso único']);
        $this->postJson('/api/v1/cursos', ['nome' => 'Curso único'])->assertStatus(422);

        $turma = Turma::factory()->create(['curso_id' => $curso->id, 'nome' => 'Turma X']);
        $this->postJson("/api/v1/cursos/{$curso->id}/turmas", ['nome' => 'Turma X'])->assertStatus(422);

        $disciplina = Disciplina::factory()->create(['turma_id' => $turma->id, 'nome' => 'Disc']);
        $this->postJson("/api/v1/turmas/{$turma->id}/disciplinas", ['nome' => 'Disc'])->assertStatus(422);

        Aula::factory()->create(['disciplina_id' => $disciplina->id, 'titulo' => 'Aula 1']);
        $this->postJson("/api/v1/disciplinas/{$disciplina->id}/aulas", ['titulo' => 'Aula 1'])->assertStatus(422);
    }

    public function test_nao_exclui_curso_com_turmas(): void
    {
        $curso = Curso::factory()->create();
        Turma::factory()->create(['curso_id' => $curso->id]);

        $this->deleteJson("/api/v1/cursos/{$curso->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('cursos', ['id' => $curso->id]);
    }

    public function test_exclui_curso_sem_turmas(): void
    {
        $curso = Curso::factory()->create();

        $this->deleteJson("/api/v1/cursos/{$curso->id}")->assertOk();
        $this->assertDatabaseMissing('cursos', ['id' => $curso->id]);
    }

    public function test_nao_exclui_turma_com_disciplinas(): void
    {
        $turma = Turma::factory()->create();
        Disciplina::factory()->create(['turma_id' => $turma->id]);

        $this->deleteJson("/api/v1/turmas/{$turma->id}")->assertStatus(409);
        $this->assertDatabaseHas('turmas', ['id' => $turma->id]);
    }

    public function test_nao_exclui_disciplina_com_aulas(): void
    {
        $disciplina = Disciplina::factory()->create();
        Aula::factory()->create(['disciplina_id' => $disciplina->id]);

        $this->deleteJson("/api/v1/disciplinas/{$disciplina->id}")->assertStatus(409);
        $this->assertDatabaseHas('disciplinas', ['id' => $disciplina->id]);
    }

    public function test_curso_inexistente_retorna_404(): void
    {
        $this->getJson('/api/v1/cursos/99999')->assertNotFound();
    }
}
