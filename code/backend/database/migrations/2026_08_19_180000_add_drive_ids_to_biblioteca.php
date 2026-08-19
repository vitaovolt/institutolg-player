<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->string('drive_folder_id', 255)->nullable()->after('nome');
        });

        Schema::table('turmas', function (Blueprint $table) {
            $table->string('drive_folder_id', 255)->nullable()->after('nome');
        });

        Schema::table('disciplinas', function (Blueprint $table) {
            $table->string('drive_folder_id', 255)->nullable()->after('nome');
        });

        Schema::table('aulas', function (Blueprint $table) {
            $table->string('drive_file_id', 255)->nullable()->after('chave_capa');
            $table->string('drive_capa_file_id', 255)->nullable()->after('drive_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->dropColumn(['drive_file_id', 'drive_capa_file_id']);
        });
        Schema::table('disciplinas', function (Blueprint $table) {
            $table->dropColumn('drive_folder_id');
        });
        Schema::table('turmas', function (Blueprint $table) {
            $table->dropColumn('drive_folder_id');
        });
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('drive_folder_id');
        });
    }
};
