<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustoArmazenamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_token_retorna_401(): void
    {
        $this->getJson('/api/v1/ops/custo-armazenamento')->assertUnauthorized();
    }

    public function test_usuario_comum_retorna_403(): void
    {
        $this->comoCoordenacao(User::factory()->create([
            'email' => 'carolina@institutolg.local',
        ]));

        $this->getJson('/api/v1/ops/custo-armazenamento')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Sem permissão.')
            ->assertJsonPath('data', null);
    }

    public function test_ops_educraft_ve_soma_so_de_videos(): void
    {
        $this->comoCoordenacao(User::factory()->create([
            'email' => 'educraft.ti@gmail.com',
        ]));

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.pode_ver_ops', true);

        $disciplina = Disciplina::factory()->create();

        Aula::factory()->enviada()->create([
            'disciplina_id' => $disciplina->id,
            'titulo' => 'Com vídeo',
            'tamanho_bytes' => 2_000_000_000,
            'chave_capa' => 'capas/1/capa.png',
        ]);
        Aula::factory()->enviada()->create([
            'disciplina_id' => $disciplina->id,
            'titulo' => 'Outro vídeo',
            'tamanho_bytes' => 500_000_000,
        ]);
        Aula::factory()->create([
            'disciplina_id' => $disciplina->id,
            'titulo' => 'Rascunho',
            'tamanho_bytes' => 9_000_000_000,
            'chave_capa' => 'capas/rascunho/capa.png',
        ]);

        $this->getJson('/api/v1/ops/custo-armazenamento')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.videos', 2)
            ->assertJsonPath('data.bytes_videos', 2_500_000_000)
            ->assertJsonPath('data.gb_videos', 2.5)
            ->assertJsonPath('data.free_tier_gb', 10)
            ->assertJsonPath('data.usd_por_gb', 0.015)
            ->assertJsonPath('data.usd_storage_estimado', 0)
            ->assertJsonPath(
                'data.aviso',
                'Estimativa só de storage R2 Standard dos vídeos (tamanho_bytes). Capas ficam de fora. Class A/B e a fatura real estão no painel Cloudflare.',
            );

        $this->assertDatabaseHas('aulas', ['titulo' => 'Rascunho', 'enviado_em' => null]);
        $this->assertDatabaseHas('aulas', ['titulo' => 'Com vídeo', 'chave_capa' => 'capas/1/capa.png']);
    }

    public function test_ops_email_case_insensitive(): void
    {
        $this->comoCoordenacao(User::factory()->create([
            'email' => 'Educraft.TI@Gmail.com',
        ]));

        $this->getJson('/api/v1/ops/custo-armazenamento')
            ->assertOk()
            ->assertJsonPath('data.videos', 0)
            ->assertJsonPath('data.bytes_videos', 0);
    }

    public function test_acima_do_free_tier_estima_usd(): void
    {
        config([
            'biblioteca.r2_storage_free_gb' => 1,
            'biblioteca.r2_storage_usd_por_gb' => 0.015,
        ]);

        $this->comoCoordenacao(User::factory()->create([
            'email' => 'educraft.ti@gmail.com',
        ]));

        Aula::factory()->enviada()->create([
            'tamanho_bytes' => 3_000_000_000,
        ]);

        $this->getJson('/api/v1/ops/custo-armazenamento')
            ->assertOk()
            ->assertJsonPath('data.gb_videos', 3)
            ->assertJsonPath('data.usd_storage_estimado', 0.03);
    }
}
