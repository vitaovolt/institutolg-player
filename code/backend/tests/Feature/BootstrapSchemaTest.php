<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BootstrapSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabelas_base_existem(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('cursos'));
        $this->assertTrue(Schema::hasTable('turmas'));
        $this->assertTrue(Schema::hasTable('disciplinas'));
        $this->assertTrue(Schema::hasTable('aulas'));
        $this->assertTrue(Schema::hasColumn('aulas', 'publicada'));
        $this->assertTrue(Schema::hasColumn('aulas', 'enviado_em'));
        $this->assertTrue(Schema::hasColumn('aulas', 'token_publico'));
        $this->assertTrue(Schema::hasColumn('aulas', 'chave_arquivo'));
        $this->assertTrue(Schema::hasColumn('aulas', 'chave_play'));
        $this->assertTrue(Schema::hasColumn('aulas', 'chave_capa'));
        $this->assertTrue(Schema::hasColumn('aulas', 'chave_idempotencia'));
        $this->assertTrue(Schema::hasColumn('users', 'ativo'));
    }
}
