<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('aulas', function (Blueprint $table) {
            $table->string('chave_arquivo', 2048)->nullable()->change();
            $table->string('chave_play', 2048)->nullable()->change();
            $table->string('chave_capa', 2048)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('aulas', function (Blueprint $table) {
            $table->string('chave_arquivo', 255)->nullable()->change();
            $table->string('chave_play', 255)->nullable()->change();
            $table->string('chave_capa', 255)->nullable()->change();
        });
    }
};
