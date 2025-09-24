<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('h_gambar_optional', function (Blueprint $table) {
            // Tambahkan kolom deskripsi setelah path_gambar_optional
            $table->text('deskripsi')->nullable()->after('path_gambar_optional');
        });
    }

    public function down(): void
    {
        Schema::table('h_gambar_optional', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });
    }
};