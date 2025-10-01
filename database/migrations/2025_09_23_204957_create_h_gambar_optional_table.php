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
            $table->foreignId('e_varian_body_id')->constrained('e_varian_body')->onDelete('cascade');
            $table->string('path_gambar_optional');
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h_gambar_optional');
    }
};
