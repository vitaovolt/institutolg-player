<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Support\CaminhoDaBiblioteca;
use App\Support\ValidarExportMp4;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_atualizar_titulo_move_play_e_capa_para_as_chaves_novas(): void
    {
        $this->comoCoordenacao();
        $aula = $this->aulaComPlayECapa('Nome antigo');
        $disciplina = $aula->disciplina;
        $videoAntigo = CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome antigo');
        $capaAntiga = CaminhoDaBiblioteca::chaveCapaPara($disciplina, 'Nome antigo', 'png');
        $videoNovo = CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome novo');
        $capaNova = CaminhoDaBiblioteca::chaveCapaPara($disciplina, 'Nome novo', 'png');
        $aulas = Storage::disk((string) config('biblioteca.disk_aulas'));

        $this->putJson("/api/v1/aulas/{$aula->id}", ['titulo' => 'Nome novo'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.titulo', 'Nome novo');

        $this->assertDatabaseHas('aulas', ['id' => $aula->id, 'titulo' => 'Nome novo']);
        $aulas->assertMissing($videoAntigo);
        $aulas->assertMissing($capaAntiga);
        $aulas->assertExists($videoNovo);
        $aulas->assertExists($capaNova);
        $this->assertSame($videoNovo, $aula->fresh()->chave_play);
        $this->assertSame($videoNovo, $aula->fresh()->chave_arquivo);
        $this->assertSame($capaNova, $aula->fresh()->chave_capa);
    }

    public function test_atualizar_titulo_move_play_e_nao_a_pasta_compartilhada(): void
    {
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
        $drive->assertMissing(CaminhoDaBiblioteca::chaveCapaPara($disciplina, 'Nome novo', 'png'));
    }

    public function test_editar_titulo_nao_chama_cliente_da_pasta(): void
    {
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
    }

    public function test_erro_403_na_pasta_nao_impede_editar_o_play(): void
    {
        $json = $this->arquivoContaDeServicoTemporario();
        config([
            'biblioteca.drive.fake' => false,
            'biblioteca.drive.pausado' => false,
            'biblioteca.drive.upload_url' => '',
            'biblioteca.drive.service_account_path' => $json,
            'biblioteca.drive.folder_id' => 'pasta-piloto',
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok-drive'], 200),
            'https://www.googleapis.com/drive/v3/*' => Http::response(['error' => ['message' => 'quota']], 403),
        ]);

        $this->comoCoordenacao();
        $aula = $this->aulaComPlayECapa('Nome antigo');
        $disciplina = $aula->disciplina;

        $this->putJson("/api/v1/aulas/{$aula->id}", ['titulo' => 'Nome novo'])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Nome novo');

        Storage::disk((string) config('biblioteca.disk_aulas'))
            ->assertExists(CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome novo'))
            ->assertMissing(CaminhoDaBiblioteca::chaveVideo($disciplina, 'Nome antigo'));

        @unlink($json);
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
}
