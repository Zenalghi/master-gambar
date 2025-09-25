<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('z_transaksi_varians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('z_transaksi_detail_id')->constrained('z_transaksi_details')->onDelete('cascade');
            $table->foreignId('e_varian_body_id')->constrained('e_varian_body');
            $table->integer('urutan'); // Untuk urutan 1, 2, 3, dst. (Standar, Varian 1, ...)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_transaksi_varians');
    }
};
