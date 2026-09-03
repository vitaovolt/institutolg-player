<?php

namespace Tests\Feature;

use Database\Seeders\BibliotecaPilotoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BibliotecaDesempenhoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comoCoordenacao();
        $this->seed(BibliotecaPilotoSeeder::class);
    }

    public function test_biblioteca_nao_inclui_campos_pesados_da_aula(): void
    {
        $response = $this->getJson('/api/v1/biblioteca');

        $response->assertOk();
        $aula = $response->json('data.0.turmas.0.disciplinas.0.aulas.0');

        $this->assertArrayHasKey('titulo', $aula);
        $this->assertArrayHasKey('status_preparo', $aula);
        $this->assertArrayNotHasKey('html_iframe', $aula);
        $this->assertArrayNotHasKey('url_player', $aula);
        $this->assertArrayNotHasKey('url_demonstracao_eduq', $aula);
        $this->assertArrayNotHasKey('mensagem_erro', $aula);
    }
}
