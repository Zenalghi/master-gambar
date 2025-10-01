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

            // ID Induk (Parent IDs) yang disimpan langsung di tabel ini
            $table->string('a_type_engine_id', 2);
            $table->string('b_merk_id', 4);
            $table->string('c_type_chassis_id', 7);
            $table->string('d_jenis_kendaraan_id', 9);
            $table->unsignedBigInteger('e_varian_body_id'); // Tetap menggunakan tipe data yang benar

            // Data spesifik untuk Gambar Optional
            $table->string('path_gambar_optional');
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            // Mendefinisikan Foreign Key Constraints untuk integritas data
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
