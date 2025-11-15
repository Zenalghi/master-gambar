<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('i_gambar_kelistrikan', function (Blueprint $table) {
            $table->id();
            // --- BERUBAH DI SINI ---
            $table->foreignId('c_type_chassis_id')->constrained('c_type_chassis')->onDelete('cascade');
            // -----------------------
            $table->string('path_gambar_kelistrikan');
            $table->text('deskripsi')->nullable();
            $table->timestamps();
            $table->softDeletes(); // <-- TAMBAHKAN INI
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('i_gambar_kelistrikan');
    }
};
