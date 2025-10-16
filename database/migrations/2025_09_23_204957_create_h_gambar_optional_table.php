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

            // Kolom Tipe untuk membedakan
            $table->enum('tipe', ['independen', 'paket'])->default('independen');

            // ID Induk (Parent IDs) untuk tipe 'independen'
            $table->string('a_type_engine_id', 2)->nullable();
            $table->string('b_merk_id', 4)->nullable();
            $table->string('c_type_chassis_id', 7)->nullable();
            $table->string('d_jenis_kendaraan_id', 9)->nullable();
            $table->unsignedBigInteger('e_varian_body_id')->nullable();

            // Relasi ke Gambar Utama untuk tipe 'paket'
            $table->foreignId('g_gambar_utama_id')->nullable()->constrained('g_gambar_utama')->onDelete('cascade');

            // Data spesifik
            $table->string('path_gambar_optional');
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            // Foreign Keys (dibuat nullable karena salah satunya akan kosong)
            $table->foreign('a_type_engine_id')->references('id')->on('a_type_engines')->onDelete('cascade');
            $table->foreign('b_merk_id')->references('id')->on('b_merks')->onDelete('cascade');
            $table->foreign('c_type_chassis_id')->references('id')->on('c_type_chassis')->onDelete('cascade');
            $table->foreign('d_jenis_kendaraan_id')->references('id')->on('d_jenis_kendaraan')->onDelete('cascade');
            $table->foreign('e_varian_body_id')->references('id')->on('e_varian_body')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h_gambar_optional');
    }
};
