<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('skrbs');
        Schema::create('skrbs', function (Blueprint $table) {
            $table->id();
            $table->string('id_skrb')->unique(); // Contoh: 03/VCLAS-SKRB/VII/2026
            $table->string('transaksi_id'); // ID dari z_transaksi bersifat string (cth: 0726-0048)
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('bulan_tahun')->index(); // Untuk filter nomor urut bulanan
            $table->integer('nomor_urut')->default(1);
            $table->string('status_tdp_snapshot')->nullable();
            $table->json('snapshot_documents')->nullable(); // Snapshot Data Umum, TDP, SUT & info kendaraan (bebas FK aktif)
            $table->json('custom_files')->nullable(); // File upload nomor 5-9 dan gambar tambahan
            $table->json('hidden_flags')->nullable(); // Status toggle ikon mata 👁️ hide/unhide
            $table->integer('fase')->default(1); // Fase 1: awal, Fase 2: tersimpan/view, Fase 3: mode edit
            $table->timestamps();

            // Relasi ke z_transaksi (string)
            $table->foreign('transaksi_id')->references('id')->on('z_transaksi')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skrbs');
    }
};
