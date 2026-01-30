<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('g_gambar_utama', function (Blueprint $table) {
            // Ubah kolom menjadi boleh KOSONG (NULL)
            $table->string('path_gambar_terurai')->nullable()->change();
            $table->string('path_gambar_kontruksi')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('g_gambar_utama', function (Blueprint $table) {
            // Kembalikan ke WAJIB ISI (NOT NULL) jika di-rollback
            $table->string('path_gambar_terurai')->nullable(false)->change();
            $table->string('path_gambar_kontruksi')->nullable(false)->change();
        });
    }
};
