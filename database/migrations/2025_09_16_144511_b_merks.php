<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('b_merks', function (Blueprint $table) {
            $table->id();
            // HAPUS foreignId('a_type_engine_id')...
            $table->string('merk'); // Biarkan string biasa, validasi unik di level request
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('b_merks');
    }
};
