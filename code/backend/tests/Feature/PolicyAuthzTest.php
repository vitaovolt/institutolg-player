<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Turma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyAuthzTest extends TestCase
{
    use RefreshDatabase;

    public function test_anônimo_nao_muta_curso_nem_publica(): void
    {
        $curso = Curso::factory()->create(['nome' => 'Original']);
        $aula = Aula::factory()->publicada()->create();

        $this->putJson('/api/v1/cursos/'.$curso->id, ['nome' => 'Hack'])->assertUnauthorized();
        $this->postJson('/api/v1/aulas/'.$aula->id.'/publicar')->assertUnauthorized();
        $this->postJson('/api/v1/aulas/'.$aula->id.'/mover', ['disciplina_id' => $aula->disciplina_id])->assertUnauthorized();
        $this->getJson('/api/v1/biblioteca')->assertUnauthorized();

        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'nome' => 'Original']);
        $this->assertTrue($aula->fresh()->publicada);
    }

    public function test_conta_inativa_recebe_403_e_nao_altera_dados(): void
    {
        $this->comoInativo();
        $curso = Curso::factory()->create(['nome' => 'Cardiologia avançada']);
        $aula = Aula::factory()->publicada()->create(['titulo' => 'Aula policy']);

        $this->putJson('/api/v1/cursos/'.$curso->id, ['nome' => 'Hackeado'])
            ->assertForbidden();
        $this->getJson('/api/v1/biblioteca')->assertForbidden();
        $this->getJson('/api/v1/resumo-mes')->assertForbidden();
        $this->getJson('/api/v1/usuarios')->assertForbidden();
        $this->postJson('/api/v1/aulas/'.$aula->id.'/despublicar')->assertForbidden();
        $this->postJson('/api/v1/aulas/'.$aula->id.'/mover', ['disciplina_id' => $aula->disciplina_id + 1])->assertForbidden();
        $this->postJson('/api/v1/cursos', ['nome' => 'Curso invasor'])->assertForbidden();

        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'nome' => 'Cardiologia avançada']);
        $this->assertDatabaseMissing('cursos', ['nome' => 'Curso invasor']);
        $this->assertTrue($aula->fresh()->publicada);
    }

    public function test_recurso_inexistente_retorna_404_autenticado(): void
    {
        $this->comoCoordenacao();

        $this->getJson('/api/v1/cursos/999999')->assertNotFound();
        $this->getJson('/api/v1/turmas/999999')->assertNotFound();
        $this->getJson('/api/v1/disciplinas/999999')->assertNotFound();
        $this->getJson('/api/v1/aulas/999999')->assertNotFound();
        $this->putJson('/api/v1/cursos/999999', ['nome' => 'X'])->assertNotFound();
        $this->deleteJson('/api/v1/aulas/999999')->assertNotFound();
    }

    public function test_coordenacao_ativa_segue_podendo_editar(): void
    {
        $this->comoCoordenacao();
        $curso = Curso::factory()->create(['nome' => 'Antes']);

        $this->putJson('/api/v1/cursos/'.$curso->id, ['nome' => 'Depois'])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Depois');

        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'nome' => 'Depois']);
    }

    public function test_conta_inativa_nao_cria_turma_disciplina_aula(): void
    {
        $this->comoInativo();
        $curso = Curso::factory()->create();
        $turma = Turma::factory()->create(['curso_id' => $curso->id]);
        $disciplina = Disciplina::factory()->create(['turma_id' => $turma->id]);

        $this->postJson('/api/v1/cursos/'.$curso->id.'/turmas', ['nome' => 'Turma invasora'])->assertForbidden();
        $this->postJson('/api/v1/turmas/'.$turma->id.'/disciplinas', ['nome' => 'Disc invasora'])->assertForbidden();
        $this->postJson('/api/v1/disciplinas/'.$disciplina->id.'/aulas', ['titulo' => 'Aula invasora'])->assertForbidden();

        $this->assertDatabaseMissing('turmas', ['nome' => 'Turma invasora']);
        $this->assertDatabaseMissing('disciplinas', ['nome' => 'Disc invasora']);
        $this->assertDatabaseMissing('aulas', ['titulo' => 'Aula invasora']);
    }
}
