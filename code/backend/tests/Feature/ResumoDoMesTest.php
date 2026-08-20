<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Disciplina;
use Database\Seeders\BibliotecaPilotoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResumoDoMesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comoCoordenacao();
    }

    public function test_seed_piloto_resume_tres_enviadas_e_duas_publicadas(): void
    {
        Carbon::setTestNow('2026-08-18 12:00:00');
        $this->seed(BibliotecaPilotoSeeder::class);

        $this->getJson('/api/v1/resumo-mes')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.competencia', '2026-08')
            ->assertJsonPath('data.enviadas', 3)
            ->assertJsonPath('data.publicadas', 2)
            ->assertJsonPath('data.enviadas_nao_publicadas', 1)
            ->assertJsonPath('data.mensalidade_painel', 287)
            ->assertJsonPath('data.preco_aula_publicada', 3.8)
            ->assertJsonPath('data.valor_aulas_publicadas', 11.4)
            ->assertJsonPath('data.total', 298.4)
            ->assertJsonPath('data.total_importadas', 3)
            ->assertJsonPath('data.aulas_por_mes.11.competencia', '2026-08')
            ->assertJsonPath('data.aulas_por_mes.11.enviadas', 3);
        $this->assertCount(12, $this->getJson('/api/v1/resumo-mes')->json('data.aulas_por_mes'));
    }

    public function test_filtra_enviadas_pelo_mes_e_mantem_total_importadas(): void
    {
        Carbon::setTestNow('2026-08-18 12:00:00');
        $disciplina = Disciplina::factory()->create();

        Aula::factory()->enviada()->create([
            'disciplina_id' => $disciplina->id,
            'titulo' => 'Julho',
            'enviado_em' => '2026-07-10 09:00:00',
        ]);
        Aula::factory()->enviada()->create([
            'disciplina_id' => $disciplina->id,
            'titulo' => 'Agosto',
            'enviado_em' => '2026-08-05 09:00:00',
        ]);

        $this->getJson('/api/v1/resumo-mes?mes=2026-07')
            ->assertOk()
            ->assertJsonPath('data.competencia', '2026-07')
            ->assertJsonPath('data.enviadas', 1)
            ->assertJsonPath('data.total_importadas', 2)
            ->assertJsonPath('data.aulas_por_mes.11.competencia', '2026-07')
            ->assertJsonPath('data.aulas_por_mes.11.enviadas', 1);

        $this->getJson('/api/v1/resumo-mes?mes=2026-08')
            ->assertOk()
            ->assertJsonPath('data.enviadas', 1)
            ->assertJsonPath('data.total_importadas', 2)
            ->assertJsonPath('data.aulas_por_mes.10.competencia', '2026-07')
            ->assertJsonPath('data.aulas_por_mes.10.enviadas', 1)
            ->assertJsonPath('data.aulas_por_mes.11.competencia', '2026-08')
            ->assertJsonPath('data.aulas_por_mes.11.enviadas', 1);

        $this->assertDatabaseHas('aulas', ['titulo' => 'Julho']);
        $this->assertDatabaseHas('aulas', ['titulo' => 'Agosto']);
    }

    public function test_aula_enviada_entra_na_cobranca_mesmo_sem_publicar(): void
    {
        Carbon::setTestNow('2026-08-18 12:00:00');
        $disciplina = Disciplina::factory()->create();

        Aula::factory()->publicada()->create([
            'disciplina_id' => $disciplina->id,
            'titulo' => 'Publicada',
        ]);
        Aula::factory()->enviada()->create([
            'disciplina_id' => $disciplina->id,
            'titulo' => 'Só enviada',
            'publicada' => false,
        ]);
        Aula::factory()->create([
            'disciplina_id' => $disciplina->id,
            'titulo' => 'Rascunho',
        ]);

        $this->getJson('/api/v1/resumo-mes')
            ->assertOk()
            ->assertJsonPath('data.enviadas', 2)
            ->assertJsonPath('data.publicadas', 1)
            ->assertJsonPath('data.enviadas_nao_publicadas', 1)
            ->assertJsonPath('data.total_importadas', 2)
            ->assertJsonPath('data.valor_aulas_publicadas', 7.6)
            ->assertJsonPath('data.total', 294.6);

        $this->assertDatabaseHas('aulas', ['titulo' => 'Só enviada', 'publicada' => false]);
        $this->assertDatabaseHas('aulas', ['titulo' => 'Publicada', 'publicada' => true]);
        $this->assertDatabaseHas('aulas', ['titulo' => 'Rascunho', 'enviado_em' => null]);
    }
}
