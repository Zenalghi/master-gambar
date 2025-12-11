<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('z_transaksi_details', function (Blueprint $table) {
            $table->id();

            // FK ke z_transaksi (String 9 karakter sesuai migrasi Anda)
            // Unique karena One-to-One
            $table->string('transaksi_id', 9)->unique();
            $table->foreign('transaksi_id')->references('id')->on('z_transaksi')->onDelete('cascade');

            $table->foreignId('pemeriksa_id')->nullable()->constrained('users');
            $table->integer('jumlah_gambar')->default(1);

            // Simpan konfigurasi gambar dalam JSON
            $table->json('data_gambar_utama')->nullable(); // Array: [{judul_id: 1, varian_id: 2}, ...]
            $table->json('data_optional_independen')->nullable(); // Array ID: [1, 2]

            $table->string('deskripsi_optional')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_transaksi_details');
    }
};
