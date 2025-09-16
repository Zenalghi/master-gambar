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
        Schema::create('e_varian_body', function (Blueprint $table) {
            $table->id(); // ID auto-increment biasa
            $table->string('jenis_kendaraan_id', 9); // Foreign key ke ID 9 digit
            $table->string('varian_body');
            $table->timestamps();

            $table->foreign('jenis_kendaraan_id')->references('id')->on('d_jenis_kendaraan')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('e_varian_body');
    }
};
