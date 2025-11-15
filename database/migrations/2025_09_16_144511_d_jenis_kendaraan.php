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
        Schema::create('d_jenis_kendaraan', function (Blueprint $table) {
            $table->id(); // <-- BERUBAH
            $table->string('jenis_kendaraan');
            $table->timestamps();
            $table->softDeletes(); // <-- TAMBAHKAN INI
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('d_jenis_kendaraan');
    }
};
