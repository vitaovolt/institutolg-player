<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->string('chave_idempotencia', 36)->nullable()->after('mensagem_erro');
            $table->string('token_upload', 64)->nullable()->after('chave_idempotencia');
            $table->string('chave_arquivo')->nullable()->after('token_upload');
            $table->string('chave_play')->nullable()->after('chave_arquivo');
            $table->unsignedBigInteger('tamanho_bytes')->nullable()->after('chave_play');

            $table->unique('chave_idempotencia');
            $table->unique('token_upload');
        });
    }

    public function down(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->dropUnique(['chave_idempotencia']);
            $table->dropUnique(['token_upload']);
            $table->dropColumn([
                'chave_idempotencia',
                'token_upload',
                'chave_arquivo',
                'chave_play',
                'tamanho_bytes',
            ]);
        });
    }
};
