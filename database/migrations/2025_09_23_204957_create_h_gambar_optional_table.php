<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h_gambar_optional', function (Blueprint $table) {
            $table->id();

            // Tipe gambar: Independen atau Paket (Dependen)
            $table->enum('tipe', ['independen', 'paket'])->default('independen');

            // Relasi utama: Ke Varian Body (Wajib ada untuk kedua tipe)
            $table->foreignId('e_varian_body_id')->constrained('e_varian_body')->onDelete('cascade');

            // Relasi tambahan: Ke Gambar Utama (Hanya jika tipe = paket)
            $table->foreignId('g_gambar_utama_id')->nullable()->constrained('g_gambar_utama')->onDelete('cascade');

            $table->string('path_gambar_optional');
            $table->text('deskripsi')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h_gambar_optional');
    }
};
