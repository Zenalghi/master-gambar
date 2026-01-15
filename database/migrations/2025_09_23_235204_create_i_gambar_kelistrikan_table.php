<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
//2025_09_23_235204_create_i_gambar_kelistrikan_table.php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('i_gambar_kelistrikan', function (Blueprint $table) {
            $table->id();

            // Menyimpan 3 ID Induk
            $table->foreignId('a_type_engine_id')->constrained('a_type_engines')->onDelete('cascade');
            $table->foreignId('b_merk_id')->constrained('b_merks')->onDelete('cascade');
            $table->foreignId('c_type_chassis_id')->constrained('c_type_chassis')->onDelete('cascade');

            $table->string('path_gambar_kelistrikan')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            // Mencegah duplikasi: 1 Sasis (dengan merk/engine tertentu) hanya boleh punya 1 gambar kelistrikan
            $table->unique(['a_type_engine_id', 'b_merk_id', 'c_type_chassis_id'], 'unique_kelistrikan_combo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('i_gambar_kelistrikan');
    }
};
