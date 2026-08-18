<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();

            $table->unique('nome');
        });

        Schema::create('turmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos');
            $table->string('nome');
            $table->timestamps();

            $table->unique(['curso_id', 'nome']);
            $table->index('curso_id');
        });

        Schema::create('disciplinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas');
            $table->string('nome');
            $table->timestamps();

            $table->unique(['turma_id', 'nome']);
            $table->index('turma_id');
        });

        Schema::create('aulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplina_id')->constrained('disciplinas');
            $table->string('titulo');
            $table->unsignedInteger('ordem')->default(1);
            $table->string('token_publico', 36);
            $table->string('status_preparo', 20)->default('rascunho');
            $table->string('status_drive', 20)->default('pendente');
            $table->boolean('publicada')->default(false);
            $table->timestamp('publicada_em')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->text('mensagem_erro')->nullable();
            $table->timestamps();

            $table->unique('token_publico');
            $table->unique(['disciplina_id', 'titulo']);
            $table->index(['disciplina_id', 'ordem']);
            $table->index('publicada');
            $table->index('enviado_em');
            $table->index('status_preparo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aulas');
        Schema::dropIfExists('disciplinas');
        Schema::dropIfExists('turmas');
        Schema::dropIfExists('cursos');
    }
};
