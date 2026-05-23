<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_master_varians', function (Blueprint $table) {
            $table->id();
            // Foreign key ke jenis kendaraan
            $table->foreignId('d_jenis_kendaraan_id')->constrained('d_jenis_kendaraan')->onDelete('cascade');
            $table->string('nama_varian');
            $table->timestamps();
            $table->softDeletes(); // Wajib ada untuk fitur tong sampah
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_master_varians');
    }
};
