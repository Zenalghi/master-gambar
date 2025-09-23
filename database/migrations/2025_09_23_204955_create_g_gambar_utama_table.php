<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('g_gambar_utama', function (Blueprint $table) {
            $table->id();
            $table->foreignId('e_varian_body_id')->constrained('e_varian_body')->onDelete('cascade');
            $table->string('path_gambar_utama');
            $table->string('path_gambar_terurai');
            $table->string('path_gambar_kontruksi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('g_gambar_utama');
    }
};
