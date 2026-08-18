<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Support\CaminhoDaBiblioteca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaminhoDaBibliotecaTest extends TestCase
{
    use RefreshDatabase;

    public function test_monta_curso_turma_disciplina_e_arquivo(): void
    {
        $curso = Curso::factory()->create(['nome' => 'Pós-graduação em Saúde']);
        $turma = Turma::factory()->create(['curso_id' => $curso->id, 'nome' => 'Turma 2026-A']);
        $disciplina = Disciplina::factory()->create(['turma_id' => $turma->id, 'nome' => 'Cardiologia']);

        $video = CaminhoDaBiblioteca::chaveVideo($disciplina, 'Aula 04 — Novo tema');
        $this->assertSame('pos-graduacao-em-saude/turma-2026-a/cardiologia/aula-04-novo-tema.mp4', $video);

        $aula = $disciplina->aulas()->create(['titulo' => 'Aula 04 — Novo tema']);
        $this->assertSame(
            'pos-graduacao-em-saude/turma-2026-a/cardiologia/aula-04-novo-tema_capa.png',
            CaminhoDaBiblioteca::chaveCapa($aula, 'png')
        );
        $this->assertSame('Aula 04 — Novo tema.mp4', CaminhoDaBiblioteca::nomeArquivoDrive($aula, 'video'));
        $this->assertSame('Aula 04 — Novo tema_capa.png', CaminhoDaBiblioteca::nomeArquivoDrive($aula, 'capa', 'png'));
    }
}
