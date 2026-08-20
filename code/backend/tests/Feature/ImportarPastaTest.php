<?php

namespace Tests\Feature;

use App\Jobs\VarreduraDaPastaCompartilhadaJob;
use App\Models\Aula;
use App\Models\Curso;
use App\Support\CaminhoDaBiblioteca;
use App\Support\RelatorioImportacaoPasta;
use App\Support\ValidarExportMp4;
use App\Support\ValidarFotoCapa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportarPastaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDiscosDaBiblioteca();
        Cache::flush();
        config(['biblioteca.drive.fake' => true, 'biblioteca.drive.folder_id' => 'pasta-piloto']);
    }

    public function test_arvore_tres_niveis_cria_cadastro_play_e_nao_publica(): void
    {
        $drive = Storage::disk((string) config('biblioteca.disk_drive'));
        $drive->put('pasta-piloto/Curso A/Turma 1/Disc 1/Aula E2.mp4', ValidarExportMp4::amostraValida());
        $drive->put('pasta-piloto/Curso A/Turma 1/Disc 1/Aula E2_capa.jpg', ValidarFotoCapa::amostraJpeg());
        $drive->put('pasta-piloto/solto.mp4', ValidarExportMp4::amostraValida());
        $drive->put('pasta-piloto/Curso A/Turma 1/Disc 1/notas/readme.txt', 'ignorar');

        $this->comoCoordenacao();
        $this->postJson('/api/v1/biblioteca/importar-pasta')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('cursos', ['nome' => 'Curso A']);
        $this->assertDatabaseHas('turmas', ['nome' => 'Turma 1']);
        $this->assertDatabaseHas('disciplinas', ['nome' => 'Disc 1']);
        $this->assertDatabaseHas('aulas', [
            'titulo' => 'Aula E2',
            'status_preparo' => 'pronta',
            'publicada' => false,
        ]);

        $aula = Aula::query()->where('titulo', 'Aula E2')->first();
        $this->assertNotNull($aula?->enviado_em);
        $this->assertNotNull($aula?->chave_play);
        $this->assertNotNull($aula?->chave_capa);
        $this->assertFalse($aula->publicada);
        Storage::disk((string) config('biblioteca.disk_aulas'))
            ->assertExists(CaminhoDaBiblioteca::chaveVideo($aula->disciplina, 'Aula E2'));

        $relatorio = RelatorioImportacaoPasta::ler();
        $this->assertSame('ok', $relatorio['status']);
        $itensIgnorados = array_column($relatorio['ignorados'], 'item');
        $this->assertContains('solto.mp4', $itensIgnorados);
        $this->assertTrue(collect($relatorio['ignorados'])->contains(
            fn (array $item): bool => str_contains($item['item'], 'notas'),
        ));
        $this->assertSame(1, Aula::query()->count());
    }

    public function test_segunda_rodada_nao_duplica(): void
    {
        $drive = Storage::disk((string) config('biblioteca.disk_drive'));
        $drive->put('pasta-piloto/Curso A/Turma 1/Disc 1/Aula E2.mp4', ValidarExportMp4::amostraValida());

        $this->comoCoordenacao();
        $this->postJson('/api/v1/biblioteca/importar-pasta')->assertOk();
        $this->postJson('/api/v1/biblioteca/importar-pasta')->assertOk();

        $this->assertSame(1, Curso::query()->count());
        $this->assertSame(1, Aula::query()->count());
        $aula = Aula::query()->first();
        $this->assertSame('pronta', $aula->status_preparo);
        $this->assertNotNull($aula->enviado_em);
    }

    public function test_submit_unico_nao_enfileira_duas_vezes(): void
    {
        Queue::fake();
        RelatorioImportacaoPasta::iniciar();

        $this->comoCoordenacao();
        $this->postJson('/api/v1/biblioteca/importar-pasta')
            ->assertOk()
            ->assertJsonPath('message', 'Já estamos importando da pasta compartilhada.');

        Queue::assertNothingPushed();
    }

    public function test_submit_unico_segundo_clique_enquanto_importa(): void
    {
        Queue::fake();
        $this->comoCoordenacao();
        $this->postJson('/api/v1/biblioteca/importar-pasta')
            ->assertOk()
            ->assertJsonPath('message', 'Importando da pasta compartilhada…');

        $this->postJson('/api/v1/biblioteca/importar-pasta')
            ->assertOk()
            ->assertJsonPath('message', 'Já estamos importando da pasta compartilhada.');

        Queue::assertPushed(VarreduraDaPastaCompartilhadaJob::class, 1);
    }

    public function test_get_relatorio_exige_login(): void
    {
        $this->getJson('/api/v1/biblioteca/importar-pasta')->assertUnauthorized();
    }

    public function test_interpreta_capa_e_mp4_pelo_nome(): void
    {
        $this->assertSame(
            ['tipo' => 'video', 'titulo' => 'Aula E2', 'extensao' => 'mp4'],
            CaminhoDaBiblioteca::interpretarArquivoDaPasta('Aula E2.mp4'),
        );
        $this->assertSame(
            ['tipo' => 'capa', 'titulo' => 'Aula E2', 'extensao' => 'jpg'],
            CaminhoDaBiblioteca::interpretarArquivoDaPasta('Aula E2_capa.jpeg'),
        );
        $this->assertNull(CaminhoDaBiblioteca::interpretarArquivoDaPasta('notas.txt'));
    }

    public function test_liga_curso_ja_cadastrado_pelo_nome(): void
    {
        $curso = Curso::factory()->create(['nome' => 'Curso A']);
        Storage::disk((string) config('biblioteca.disk_drive'))
            ->put('pasta-piloto/Curso A/Turma 1/Disc 1/Aula E2.mp4', ValidarExportMp4::amostraValida());

        $this->comoCoordenacao();
        $this->postJson('/api/v1/biblioteca/importar-pasta')->assertOk();

        $this->assertSame(1, Curso::query()->count());
        $this->assertNotNull($curso->fresh()->drive_folder_id);
        $this->assertDatabaseHas('aulas', ['titulo' => 'Aula E2', 'status_preparo' => 'pronta']);
    }
}