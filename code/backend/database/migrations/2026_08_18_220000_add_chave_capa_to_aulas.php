<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->string('chave_capa')->nullable()->after('chave_play');
        });
    }

    public function down(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->dropColumn('chave_capa');
        });
    }
};
