<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->string('s3_upload_id', 512)->nullable()->after('token_upload');
        });
    }

    public function down(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->dropColumn('s3_upload_id');
        });
    }
};
