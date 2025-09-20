<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('f_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('varian_body_id')->constrained('e_varian_body')->onDelete('cascade');
            $table->string('jenis_pengajuan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('f_pengajuan');
    }
};