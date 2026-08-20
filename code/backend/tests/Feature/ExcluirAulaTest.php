<?php

namespace Tests\Feature;

use App\Jobs\LimparObjetosDaAulaJob;
use App\Models\Aula;
use App\Support\ValidarExportMp4;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_excluir_aula_enviando_some_do_banco_mesmo_se_o_abortar_falhar(): void
    {
        $this->comoCoordenacao();
        Queue::fake();
        $this->mock(\App\Contracts\AssinadorDeUploadDireto::class, function ($mock): void {
            $mock->shouldReceive('abortar')->andThrow(new \RuntimeException('timeout ao abortar envio'));
        });

        $aula = Aula::factory()->create([
            'titulo' => 'Aula 36',
            'status_preparo' => 'enviando',
            's3_upload_id' => 'up-preso',
            'token_upload' => Str::random(64),
            'chave_arquivo' => 'origem/36/video.mp4',
        ]);

        $this->deleteJson("/api/v1/aulas/{$aula->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('aulas', ['id' => $aula->id]);
        Queue::assertPushed(LimparObjetosDaAulaJob::class, function (LimparObjetosDaAulaJob $job) use ($aula): bool {
            return ($job->snapshot['s3_upload_id'] ?? null) === 'up-preso'
                && ($job->snapshot['chave_arquivo'] ?? null) === $aula->chave_arquivo;
        });
    }
}
