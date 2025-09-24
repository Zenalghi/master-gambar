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
            // Relasi one-to-one ke transaksi utama
            $table->string('z_transaksi_id')->unique();
            // User yang bertugas sebagai pemeriksa
            $table->foreignId('pemeriksa_id')->constrained('users');
            $table->timestamps();

            $table->foreign('z_transaksi_id')->references('id')->on('z_transaksi')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_transaksi_details');
    }
};
