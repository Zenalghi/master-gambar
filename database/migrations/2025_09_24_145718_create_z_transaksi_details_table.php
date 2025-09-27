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
            $table->string('z_transaksi_id')->unique();
            $table->foreignId('pemeriksa_id')->constrained('users');
            $table->foreignId('i_gambar_kelistrikan_id')->nullable()->constrained('i_gambar_kelistrikan')->nullOnDelete();
            $table->timestamps();
            $table->foreign('z_transaksi_id')->references('id')->on('z_transaksi')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_transaksi_details');
    }
};
