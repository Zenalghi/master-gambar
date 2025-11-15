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
            $table->foreignId('z_transaksi_id')->constrained('z_transaksi')->onDelete('cascade');
            $table->foreignId('pemeriksa_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('i_gambar_kelistrikan_id')->nullable()->constrained('i_gambar_kelistrikan')->nullOnDelete()->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_transaksi_details');
    }
};
