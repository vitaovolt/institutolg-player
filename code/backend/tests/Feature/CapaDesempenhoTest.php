<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Support\UrlTemporariaDaBiblioteca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CapaDesempenhoTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_capa_no_disco_local_usa_rota_laravel(): void
    {
        $aula = Aula::factory()->create([
            'chave_capa' => 'curso/aula_capa.png',
        ]);

        $this->assertSame(url('/capa/'.$aula->token_publico), $aula->urlCapa());
        $this->assertFalse(UrlTemporariaDaBiblioteca::discoEhObjeto());
    }

    public function test_capa_no_objeto_redireciona_para_url_temporaria(): void
    {
        config([
            'biblioteca.disk_aulas' => 'aulas',
            'filesystems.disks.aulas.driver' => 's3',
            'biblioteca.play_ttl_minutos' => 30,
        ]);

        $aula = Aula::factory()->create([
            'chave_capa' => 'curso/aula_capa.png',
        ]);
        $assinada = 'https://objetos.exemplo.test/curso/aula_capa.png?X-Amz-Signature=abc';

        Storage::shouldReceive('disk')
            ->with('aulas')
            ->andReturn($disco = \Mockery::mock());
        $disco->shouldReceive('temporaryUrl')->andReturn($assinada);

        $this->get('/capa/'.$aula->token_publico)
            ->assertRedirect($assinada);
        $this->assertSame($assinada, $aula->urlCapa());
    }

    public function test_capa_local_nao_baixa_o_arquivo_para_descobrir_o_mime(): void
    {
        $this->fakeDiscosDaBiblioteca();
        $aula = Aula::factory()->create([
            'chave_capa' => 'curso/aula_capa.png',
        ]);
        Storage::disk((string) config('biblioteca.disk_aulas'))->put($aula->chave_capa, 'nao-e-png-real');

        $this->get('/capa/'.$aula->token_publico)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
}
