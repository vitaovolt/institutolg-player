<?php

namespace Tests\Feature;

use App\Actions\RetomarEnvioDaAula;
use App\Jobs\PrepararVersaoDaAulaJob;
use App\Models\Aula;
use App\Models\Disciplina;
use App\Support\CaminhoDaBiblioteca;
use App\Support\LerInicioDoArquivoDaBiblioteca;
use App\Support\ValidarExportMp4;
use Database\Seeders\BibliotecaPilotoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnvioAulaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('biblioteca.disk_aulas'));
        Storage::fake((string) config('biblioteca.disk_drive'));
    }

    public function test_iniciar_envio_sem_token_retorna_401(): void
    {
        $disciplina = Disciplina::factory()->create();

        $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", [
            'titulo' => 'Aula nova',
            'chave_idempotencia' => (string) Str::uuid(),
        ])->assertUnauthorized();
    }

    public function test_disciplina_show_traz_turma_e_curso(): void
    {
        $this->comoCoordenacao();
        $this->seed(BibliotecaPilotoSeeder::class);
        $disciplina = Disciplina::query()->where('nome', 'Cardiologia')->firstOrFail();

        $this->getJson("/api/v1/disciplinas/{$disciplina->id}")
            ->assertOk()
            ->assertJsonPath('data.nome', 'Cardiologia')
            ->assertJsonPath('data.turma.nome', 'Turma 2026-A')
            ->assertJsonPath('data.turma.curso.nome', 'Pós-graduação em Saúde');
    }

    public function test_fluxo_completo_grava_arquivo_enfileira_e_fica_pronta(): void
    {
        $this->comoCoordenacao();
        $disciplina = Disciplina::factory()->create();
        $chave = (string) Str::uuid();
        $mp4 = ValidarExportMp4::amostraValida();

        $iniciar = $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", [
            'titulo' => 'Aula 04 — Novo tema',
            'chave_idempotencia' => $chave,
            'tamanho_bytes' => strlen($mp4),
        ], ['Idempotency-Key' => $chave]);

        $iniciar->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.aula.titulo', 'Aula 04 — Novo tema')
            ->assertJsonPath('data.aula.status_preparo', 'enviando')
            ->assertJsonPath('data.upload_method', 'PUT');

        $aulaId = $iniciar->json('data.aula.id');
        $uploadPath = $iniciar->json('data.upload_path');
        $this->assertNotEmpty($uploadPath);
        $this->assertDatabaseHas('aulas', [
            'id' => $aulaId,
            'titulo' => 'Aula 04 — Novo tema',
            'status_preparo' => 'enviando',
            'chave_idempotencia' => $chave,
        ]);

        $this->call('PUT', '/api/v1'.$uploadPath, server: [
            'CONTENT_TYPE' => 'video/mp4',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $mp4)->assertOk();

        $aula = Aula::query()->findOrFail($aulaId);
        Storage::disk((string) config('biblioteca.disk_aulas'))->assertExists($aula->chave_arquivo);
        $this->assertSame(strlen($mp4), $aula->tamanho_bytes);
        $this->assertSame(
            CaminhoDaBiblioteca::chaveVideo($disciplina->load('turma.curso'), 'Aula 04 — Novo tema'),
            $aula->chave_arquivo
        );

        Queue::fake();

        $this->postJson("/api/v1/aulas/{$aulaId}/envios/concluir")
            ->assertOk()
            ->assertJsonPath('data.status_preparo', 'preparando');

        $this->assertDatabaseHas('aulas', [
            'id' => $aulaId,
            'status_preparo' => 'preparando',
        ]);
        $this->assertNotNull(Aula::query()->find($aulaId)?->enviado_em);
        Queue::assertPushedOn('biblioteca', PrepararVersaoDaAulaJob::class);
        Queue::assertPushed(PrepararVersaoDaAulaJob::class, fn (PrepararVersaoDaAulaJob $job) => $job->aulaId === $aulaId);
        Queue::assertNotPushed(\App\Jobs\CopiarAulaParaDriveJob::class);

        Queue::fake();
        $this->postJson("/api/v1/aulas/{$aulaId}/envios/concluir")->assertOk();
        Queue::assertNothingPushed();
    }

    public function test_job_deixa_aula_pronta_com_versao_para_assistir(): void
    {
        $this->comoCoordenacao();
        $disciplina = Disciplina::factory()->create();
        $mp4 = ValidarExportMp4::amostraValida();
        $chave = (string) Str::uuid();

        $iniciar = $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", [
            'titulo' => 'Semiologia',
            'chave_idempotencia' => $chave,
        ])->assertCreated();

        $aulaId = $iniciar->json('data.aula.id');
        $this->call('PUT', '/api/v1'.$iniciar->json('data.upload_path'), server: [
            'CONTENT_TYPE' => 'video/mp4',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $mp4)->assertOk();

        $this->postJson("/api/v1/aulas/{$aulaId}/envios/concluir")->assertOk();

        $aula = Aula::query()->findOrFail($aulaId);
        $aula->load('disciplina.turma.curso');
        $this->assertSame('pronta', $aula->status_preparo);
        $this->assertTrue($aula->publicada);
        $this->assertNotNull($aula->publicada_em);
        $this->assertSame('pendente', $aula->status_drive);
        $this->assertNotEmpty($aula->chave_play);
        $this->assertSame($aula->chave_arquivo, $aula->chave_play);
        $this->assertNull($aula->token_upload);
        Storage::disk((string) config('biblioteca.disk_aulas'))->assertExists($aula->chave_play);
        Storage::disk((string) config('biblioteca.disk_drive'))
            ->assertMissing(CaminhoDaBiblioteca::chaveVideo($aula->disciplina, 'Semiologia'));

        $show = $this->getJson("/api/v1/aulas/{$aulaId}")->assertOk();
        $show->assertJsonPath('data.status_preparo', 'pronta')
            ->assertJsonPath('data.pronta_para_assistir', true)
            ->assertJsonPath('data.publicada', true);
        $this->assertArrayNotHasKey('chave_play', $show->json('data'));
        $this->assertArrayNotHasKey('token_upload', $show->json('data'));
    }

    public function test_mesmo_idempotency_key_nao_duplica_aula(): void
    {
        $this->comoCoordenacao();
        $disciplina = Disciplina::factory()->create();
        $chave = (string) Str::uuid();
        $payload = [
            'titulo' => 'Casos clínicos extra',
            'chave_idempotencia' => $chave,
        ];

        $primeiro = $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", $payload)->assertCreated();
        $segundo = $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", $payload)->assertOk();

        $this->assertSame($primeiro->json('data.aula.id'), $segundo->json('data.aula.id'));
        $this->assertSame(1, Aula::query()->where('titulo', 'Casos clínicos extra')->count());
    }

    public function test_put_que_nao_e_mp4_retorna_422_e_nao_grava(): void
    {
        $this->comoCoordenacao();
        $disciplina = Disciplina::factory()->create();
        $iniciar = $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", [
            'titulo' => 'Arquivo errado',
            'chave_idempotencia' => (string) Str::uuid(),
        ])->assertCreated();

        $aula = Aula::query()->findOrFail($iniciar->json('data.aula.id'));

        $this->call('PUT', '/api/v1'.$iniciar->json('data.upload_path'), server: [
            'CONTENT_TYPE' => 'video/mp4',
            'HTTP_ACCEPT' => 'application/json',
        ], content: 'isto-nao-e-mp4')->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', ValidarExportMp4::mensagemRecusa());

        Storage::disk((string) config('biblioteca.disk_aulas'))->assertMissing($aula->chave_arquivo);

        $this->call('PUT', '/api/v1'.$iniciar->json('data.upload_path'), server: [
            'CONTENT_TYPE' => 'video/quicktime',
            'HTTP_ACCEPT' => 'application/json',
        ], content: ValidarExportMp4::amostraMov())->assertStatus(422);

        Storage::disk((string) config('biblioteca.disk_aulas'))->assertMissing($aula->chave_arquivo);
    }

    public function test_tamanho_acima_do_limite_nao_cria_envio(): void
    {
        $this->comoCoordenacao();
        config(['biblioteca.upload_max_bytes' => 64]);
        $disciplina = Disciplina::factory()->create();

        $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", [
            'titulo' => 'Arquivo enorme',
            'chave_idempotencia' => (string) Str::uuid(),
            'tamanho_bytes' => 65,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('aulas', ['titulo' => 'Arquivo enorme']);
    }

    public function test_job_sem_arquivo_marca_erro(): void
    {
        $aula = Aula::factory()->create([
            'status_preparo' => 'preparando',
            'chave_arquivo' => 'origens/sumiu.mp4',
        ]);

        (new PrepararVersaoDaAulaJob($aula->id))->handle(app(\App\Actions\PrepararVersaoDaAula::class));

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'status_preparo' => 'erro',
        ]);
        $this->assertNotEmpty($aula->fresh()->mensagem_erro);
    }

    public function test_reprocessar_so_em_erro_com_arquivo(): void
    {
        $this->comoCoordenacao();
        Queue::fake();
        $disk = (string) config('biblioteca.disk_aulas');
        $aula = Aula::factory()->create([
            'status_preparo' => 'erro',
            'chave_arquivo' => 'origens/retry.mp4',
            'mensagem_erro' => 'falhou',
        ]);
        Storage::disk($disk)->put($aula->chave_arquivo, ValidarExportMp4::amostraValida());

        $this->postJson("/api/v1/aulas/{$aula->id}/envios/reprocessar")
            ->assertOk()
            ->assertJsonPath('data.status_preparo', 'preparando');

        Queue::assertPushedOn('biblioteca', PrepararVersaoDaAulaJob::class);
        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'status_preparo' => 'preparando']);
    }

    public function test_concluir_sem_arquivo_retorna_422(): void
    {
        $this->comoCoordenacao();
        $disciplina = Disciplina::factory()->create();
        $iniciar = $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", [
            'titulo' => 'Sem arquivo',
            'chave_idempotencia' => (string) Str::uuid(),
        ])->assertCreated();

        $this->postJson('/api/v1/aulas/'.$iniciar->json('data.aula.id').'/envios/concluir')
            ->assertStatus(422);
    }

    public function test_comando_biblioteca_fila_existe(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('biblioteca:fila')
            ->assertSuccessful();
    }

    public function test_teto_padrao_e_35gb(): void
    {
        $this->assertSame(35 * 1024 * 1024 * 1024, (int) config('biblioteca.upload_max_bytes'));
        $this->comoCoordenacao();
        $disciplina = Disciplina::factory()->create();

        $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", [
            'titulo' => 'No teto',
            'chave_idempotencia' => (string) Str::uuid(),
            'tamanho_bytes' => 35 * 1024 * 1024 * 1024,
        ])->assertCreated();

        $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", [
            'titulo' => 'Acima do teto',
            'chave_idempotencia' => (string) Str::uuid(),
            'tamanho_bytes' => 35 * 1024 * 1024 * 1024 + 1,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('aulas', ['titulo' => 'Acima do teto']);
    }

    public function test_multipart_grava_quando_o_disco_e_objeto(): void
    {
        $this->comoCoordenacao();
        config(['filesystems.disks.aulas.driver' => 's3']);
        $this->mock(\App\Contracts\AssinadorDeUploadDireto::class, function ($mock): void {
            $mock->shouldReceive('iniciar')->once()->andReturn('up-1');
            $mock->shouldReceive('urlDaParte')->once()->andReturn('https://objeto.test/parte-1');
            $mock->shouldReceive('completar')->once()->andReturnUsing(function (Aula $aula, array $partes): void {
                Storage::disk((string) config('biblioteca.disk_aulas'))
                    ->put($aula->chave_arquivo, ValidarExportMp4::amostraValida());
            });
            $mock->shouldReceive('abortar')->zeroOrMoreTimes();
        });

        $disciplina = Disciplina::factory()->create();
        $iniciar = $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", [
            'titulo' => 'Aula por partes',
            'chave_idempotencia' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('data.upload_method', 'multipart')
            ->assertJsonPath('data.part_size', 100 * 1024 * 1024);

        $token = basename((string) $iniciar->json('data.upload_path'));
        $this->postJson("/api/v1/envios/{$token}/partes", ['part_number' => 1])
            ->assertOk()
            ->assertJsonPath('data.url', 'https://objeto.test/parte-1');

        $this->postJson("/api/v1/envios/{$token}/completar-multipart", [
            'parts' => [['part_number' => 1, 'etag' => '"abc123"']],
        ])->assertOk();

        $aula = Aula::query()->findOrFail($iniciar->json('data.aula.id'));
        Storage::disk((string) config('biblioteca.disk_aulas'))->assertExists($aula->chave_arquivo);
        $this->assertSame(strlen(ValidarExportMp4::amostraValida()), $aula->tamanho_bytes);
        $this->assertNull($aula->s3_upload_id);
    }

    public function test_multipart_fecha_mesmo_se_completar_estourar_depois_do_objeto_pronto(): void
    {
        $this->comoCoordenacao();
        config(['filesystems.disks.aulas.driver' => 's3']);
        $this->mock(\App\Contracts\AssinadorDeUploadDireto::class, function ($mock): void {
            $mock->shouldReceive('iniciar')->once()->andReturn('up-timeout');
            $mock->shouldReceive('urlDaParte')->once()->andReturn('https://objeto.test/parte-1');
            $mock->shouldReceive('completar')->once()->andReturnUsing(function (Aula $aula): void {
                Storage::disk((string) config('biblioteca.disk_aulas'))
                    ->put($aula->chave_arquivo, ValidarExportMp4::amostraValida());
                throw new \RuntimeException('timeout ao fechar partes');
            });
            $mock->shouldReceive('abortar')->zeroOrMoreTimes();
        });

        $disciplina = Disciplina::factory()->create();
        $iniciar = $this->postJson("/api/v1/disciplinas/{$disciplina->id}/envios", [
            'titulo' => 'Aula 12GB',
            'chave_idempotencia' => (string) Str::uuid(),
        ])->assertCreated();

        $token = basename((string) $iniciar->json('data.upload_path'));
        $this->postJson("/api/v1/envios/{$token}/partes", ['part_number' => 1])->assertOk();
        $this->postJson("/api/v1/envios/{$token}/completar-multipart", [
            'parts' => [['part_number' => 1, 'etag' => '"abc123"']],
        ])->assertOk();

        $aula = Aula::query()->findOrFail($iniciar->json('data.aula.id'));
        Storage::disk((string) config('biblioteca.disk_aulas'))->assertExists($aula->chave_arquivo);
        $this->assertSame(strlen(ValidarExportMp4::amostraValida()), $aula->tamanho_bytes);
        $this->assertNull($aula->s3_upload_id);
    }

    public function test_le_so_o_inicio_do_arquivo(): void
    {
        $mp4 = ValidarExportMp4::amostraValida();
        Storage::disk((string) config('biblioteca.disk_aulas'))->put('aula-grande.mp4', $mp4.str_repeat('x', 1000));

        $inicio = LerInicioDoArquivoDaBiblioteca::bytes('aula-grande.mp4', 32);
        $this->assertSame(substr($mp4.str_repeat('x', 1000), 0, 32), $inicio);
        $this->assertTrue(ValidarExportMp4::pareceMp4($inicio));

        $fluxo = LerInicioDoArquivoDaBiblioteca::stream('aula-grande.mp4');
        $this->assertTrue(is_resource($fluxo));
        $this->assertSame($mp4, fread($fluxo, strlen($mp4)));
        fclose($fluxo);
    }

    public function test_retoma_envio_quando_o_arquivo_ja_esta_no_disco(): void
    {
        $mp4 = ValidarExportMp4::amostraValida();
        $aula = Aula::factory()->create([
            'status_preparo' => 'enviando',
            'chave_arquivo' => 'curso/turma/disc/aula.mp4',
            'token_upload' => 'tok-retoma',
            's3_upload_id' => 'up-ja-completo',
        ]);
        Storage::disk((string) config('biblioteca.disk_aulas'))->put($aula->chave_arquivo, $mp4);

        $pronta = app(RetomarEnvioDaAula::class)->handle($aula);

        $this->assertSame('pronta', $pronta->status_preparo);
        $this->assertSame($aula->chave_arquivo, $pronta->chave_play);
        $this->assertSame(strlen($mp4), $pronta->tamanho_bytes);
        $this->assertNull($pronta->s3_upload_id);
        $this->assertNull($pronta->token_upload);
    }
}
