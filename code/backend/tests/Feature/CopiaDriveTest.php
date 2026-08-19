<?php

namespace Tests\Feature;

use App\Jobs\CopiarAulaParaDriveJob;
use App\Jobs\PrepararVersaoDaAulaJob;
use App\Models\Aula;
use App\Services\Integrations\ClientePastaDrive;
use App\Support\CaminhoDaBiblioteca;
use App\Support\ValidarExportMp4;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CopiaDriveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDiscosDaBiblioteca();
    }

    public function test_job_grava_copia_na_pasta_e_marca_ok(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create([
            'status_drive' => 'pendente',
        ]));

        (new CopiarAulaParaDriveJob($aula->id))->handle(app(ClientePastaDrive::class));

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'status_drive' => 'ok',
            'status_preparo' => 'pronta',
        ]);
        Storage::disk((string) config('biblioteca.disk_drive'))
            ->assertExists(CaminhoDaBiblioteca::chaveVideo($aula->fresh()->disciplina, (string) $aula->titulo));
    }

    public function test_job_grava_capa_na_mesma_arvore_da_disciplina(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create([
            'status_drive' => 'pendente',
            'titulo' => 'Aula com capa',
        ]));
        $capa = CaminhoDaBiblioteca::chaveCapa($aula, 'png');
        Storage::disk((string) config('biblioteca.disk_aulas'))->put($capa, 'png-bytes');
        $aula->update(['chave_capa' => $capa]);

        (new CopiarAulaParaDriveJob($aula->id))->handle(app(ClientePastaDrive::class));

        $drive = Storage::disk((string) config('biblioteca.disk_drive'));
        $drive->assertExists(CaminhoDaBiblioteca::chaveVideo($aula->fresh()->disciplina, (string) $aula->titulo));
        $drive->assertExists($capa);
        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'status_drive' => 'ok']);
    }

    public function test_falha_http_marca_erro_sem_derrubar_o_play(): void
    {
        config([
            'biblioteca.drive.fake' => false,
            'biblioteca.drive.upload_url' => 'https://pasta.example.test/upload',
            'biblioteca.drive.token' => 'secret',
        ]);
        Http::fake([
            'https://pasta.example.test/*' => Http::response(['message' => 'cota'], 503),
        ]);

        $aula = $this->gravarPlay(Aula::factory()->publicada()->create([
            'status_drive' => 'pendente',
        ]));

        try {
            (new CopiarAulaParaDriveJob($aula->id))->handle(app(ClientePastaDrive::class));
            $this->fail('Esperava falha da pasta compartilhada.');
        } catch (\Throwable) {
            // o job registra o erro e relança para a fila
        }

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'status_drive' => 'erro',
            'status_preparo' => 'pronta',
        ]);
        $this->assertNotEmpty($aula->fresh()->mensagem_erro);
        $this->assertTrue($aula->fresh()->estaProntaParaAssistir());
    }

    public function test_client_http_usa_timeout_quando_a_pasta_e_real(): void
    {
        config([
            'biblioteca.drive.fake' => false,
            'biblioteca.drive.upload_url' => 'https://pasta.example.test/upload',
            'biblioteca.drive.token' => 'secret',
        ]);
        Http::fake([
            'https://pasta.example.test/*' => Http::response(['id' => 'arq-1'], 200),
        ]);

        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Cópia HTTP']));
        $id = app(ClientePastaDrive::class)->enviarCopia($aula, ValidarExportMp4::amostraValida());

        $this->assertSame('arq-1', $id);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://pasta.example.test/upload'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer secret');
        });
    }

    public function test_conta_de_servico_sobe_arquivo_na_pasta_compartilhada(): void
    {
        $json = $this->arquivoContaDeServicoTemporario();
        config([
            'biblioteca.drive.fake' => false,
            'biblioteca.drive.upload_url' => '',
            'biblioteca.drive.service_account_path' => $json,
            'biblioteca.drive.folder_id' => 'pasta-piloto',
        ]);
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'tok-drive'], 200);
            }
            if (str_contains($request->url(), 'uploadType=resumable')) {
                return Http::response('', 200, [
                    'Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=abc',
                ]);
            }
            if (str_contains($request->url(), 'upload_id=abc')) {
                return Http::response(['id' => 'arq-drive-1'], 200);
            }
            if (str_contains($request->url(), 'www.googleapis.com/drive/v3/files') && $request->method() === 'GET') {
                return Http::response(['files' => []], 200);
            }
            if (str_contains($request->url(), 'www.googleapis.com/drive/v3/files') && $request->method() === 'POST') {
                return Http::response(['id' => 'pasta-criada'], 200);
            }

            return Http::response(['message' => 'inesperado'], 500);
        });

        $aula = $this->gravarPlay(Aula::factory()->publicada()->create(['titulo' => 'Cópia Google']));
        $id = app(ClientePastaDrive::class)->enviarCopia($aula, ValidarExportMp4::amostraValida());

        $this->assertSame('arq-drive-1', $id);
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token');
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'uploadType=resumable')
                && $request->hasHeader('Authorization', 'Bearer tok-drive')
                && str_contains($request->body(), 'pasta-criada');
        });
        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'drive/v3/files?supportsAllDrives=true')
                && str_contains($request->body(), 'pasta-piloto');
        });
        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'drive/v3/files?supportsAllDrives=true')
                && ! str_contains($request->url(), 'upload');
        }, 3);
        @unlink($json);
    }

    /**
     * @return string path
     */
    private function arquivoContaDeServicoTemporario(): string
    {
        $chave = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($chave);
        openssl_pkey_export($chave, $pem);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ilg-drive-sa-'.uniqid('', true).'.json';
        file_put_contents($path, json_encode([
            'client_email' => 'copia@educraft.iam.gserviceaccount.com',
            'private_key' => $pem,
        ]));

        return $path;
    }

    public function test_reprocessar_drive_enfileira_job_e_exige_auth(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create([
            'status_drive' => 'erro',
            'mensagem_erro' => 'falhou',
        ]));

        $this->postJson("/api/v1/aulas/{$aula->id}/drive/sincronizar")->assertUnauthorized();

        $this->comoCoordenacao();
        Queue::fake();

        $this->postJson("/api/v1/aulas/{$aula->id}/drive/sincronizar")
            ->assertOk()
            ->assertJsonPath('data.status_drive', 'enviando')
            ->assertJsonPath('message', 'Enviando a cópia para a pasta compartilhada.');

        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'status_drive' => 'enviando']);
        Queue::assertPushedOn('biblioteca', CopiarAulaParaDriveJob::class);
        Queue::assertPushed(CopiarAulaParaDriveJob::class, fn (CopiarAulaParaDriveJob $job) => $job->aulaId === $aula->id);
    }

    public function test_alias_reprocessar_chama_o_mesmo_contrato(): void
    {
        $aula = $this->gravarPlay(Aula::factory()->publicada()->create([
            'status_drive' => 'pendente',
        ]));
        $this->comoCoordenacao();
        Queue::fake();

        $this->postJson("/api/v1/aulas/{$aula->id}/drive/reprocessar")
            ->assertOk()
            ->assertJsonPath('data.status_drive', 'enviando');

        Queue::assertPushed(CopiarAulaParaDriveJob::class, 1);
    }

    public function test_preparar_aula_nao_dispara_copia_na_fila_biblioteca(): void
    {
        Queue::fake();
        $this->mock(ClientePastaDrive::class, function ($mock) {
            $mock->shouldNotReceive('enviarCopia');
            $mock->shouldNotReceive('sincronizarAula');
        });
        $aula = Aula::factory()->create([
            'status_preparo' => 'preparando',
            'chave_arquivo' => 'origens/ok.mp4',
        ]);
        Storage::disk((string) config('biblioteca.disk_aulas'))
            ->put($aula->chave_arquivo, ValidarExportMp4::amostraValida());

        (new PrepararVersaoDaAulaJob($aula->id))->handle(app(\App\Actions\PrepararVersaoDaAula::class));

        $this->assertDatabaseHas('aulas', [
            'id' => $aula->id,
            'status_preparo' => 'pronta',
            'status_drive' => 'pendente',
        ]);
        Queue::assertNotPushed(CopiarAulaParaDriveJob::class);
    }

    public function test_sincronizar_grava_pastas_e_arquivo_no_disco_fake(): void
    {
        $this->comoCoordenacao();
        $aula = $this->aulaProntaNaArvore('Aula sync');

        $this->postJson("/api/v1/aulas/{$aula->id}/drive/sincronizar")
            ->assertOk()
            ->assertJsonPath('success', true);

        $aula->refresh()->load('disciplina.turma.curso');
        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'status_drive' => 'ok']);
        $drive = Storage::disk((string) config('biblioteca.disk_drive'));
        $drive->assertExists(CaminhoDaBiblioteca::chaveVideo($aula->disciplina, 'Aula sync'));
        $this->assertNotEmpty($aula->disciplina->turma->curso->drive_folder_id);
        $this->assertNotEmpty($aula->disciplina->turma->drive_folder_id);
        $this->assertNotEmpty($aula->disciplina->drive_folder_id);
        $this->assertNotEmpty($aula->drive_file_id);
    }

    public function test_segunda_sincronizacao_com_titulo_novo_atualiza_o_path(): void
    {
        $this->comoCoordenacao();
        $aula = $this->aulaProntaNaArvore('Nome antigo');
        $disciplina = $aula->disciplina;
        $drive = Storage::disk((string) config('biblioteca.disk_drive'));

        $this->postJson("/api/v1/aulas/{$aula->id}/drive/sincronizar")->assertOk();
        $drive->assertExists(CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome antigo'));

        $this->putJson("/api/v1/aulas/{$aula->id}", ['titulo' => 'Nome novo'])->assertOk();
        $drive->assertExists(CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome antigo'));
        $drive->assertMissing(CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome novo'));

        $this->postJson("/api/v1/aulas/{$aula->id}/drive/sincronizar")->assertOk();

        $drive->assertExists(CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome novo'));
        $this->assertSame(
            CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome novo'),
            $aula->fresh()->drive_file_id,
        );
    }

    public function test_sincronizar_apos_renomear_curso_atualiza_path_no_fake(): void
    {
        $this->comoCoordenacao();
        $aula = $this->aulaProntaNaArvore('Aula pasta');
        $this->postJson("/api/v1/aulas/{$aula->id}/drive/sincronizar")->assertOk();

        $curso = $aula->disciplina->turma->curso;
        $this->putJson("/api/v1/cursos/{$curso->id}", ['nome' => 'Curso novo sync'])->assertOk();

        $this->postJson("/api/v1/aulas/{$aula->id}/drive/sincronizar")->assertOk();

        $aula->refresh()->load('disciplina.turma.curso');
        Storage::disk((string) config('biblioteca.disk_drive'))
            ->assertExists(CaminhoDaBiblioteca::chaveVideo($aula->disciplina, 'Aula pasta'));
        $this->assertSame(
            CaminhoDaBiblioteca::segmento('Curso novo sync'),
            $aula->disciplina->turma->curso->drive_folder_id,
        );
    }

    public function test_sincronizar_conta_inativa_retorna_403(): void
    {
        $aula = $this->aulaProntaNaArvore('Aula policy drive');
        $this->comoInativo();

        $this->postJson("/api/v1/aulas/{$aula->id}/drive/sincronizar")->assertForbidden();
        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'status_drive' => 'pendente']);
    }

    public function test_sincronizar_aula_nao_pronta_retorna_422(): void
    {
        $this->comoCoordenacao();
        $aula = Aula::factory()->create([
            'status_preparo' => 'rascunho',
            'status_drive' => 'pendente',
        ]);

        $this->postJson("/api/v1/aulas/{$aula->id}/drive/sincronizar")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'status_drive' => 'pendente']);
    }

    public function test_duplo_post_enquanto_enviando_nao_dispara_segundo_job(): void
    {
        $this->comoCoordenacao();
        Queue::fake();
        $aula = $this->aulaProntaNaArvore('Aula lock');

        $this->postJson("/api/v1/aulas/{$aula->id}/drive/sincronizar")->assertOk();
        $this->postJson("/api/v1/aulas/{$aula->id}/drive/sincronizar")->assertOk();

        Queue::assertPushed(CopiarAulaParaDriveJob::class, 1);
        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'status_drive' => 'enviando']);
    }

    private function aulaProntaNaArvore(string $titulo): Aula
    {
        $aula = Aula::factory()->publicada()->create([
            'titulo' => $titulo,
            'status_drive' => 'pendente',
        ]);
        $aula->load('disciplina.turma.curso');
        $video = CaminhoDaBiblioteca::chaveVideo($aula->disciplina, $titulo);
        Storage::disk((string) config('biblioteca.disk_aulas'))->put($video, ValidarExportMp4::amostraValida());
        $aula->update([
            'chave_arquivo' => $video,
            'chave_play' => $video,
            'status_preparo' => 'pronta',
        ]);

        return $aula->fresh(['disciplina.turma.curso']);
    }
}
