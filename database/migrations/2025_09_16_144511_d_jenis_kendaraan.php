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
            $table->string('id', 9)->primary(); // ID 9 digit (7 sasis + 2 jenis), cth: '010100101'
            $table->string('jenis_kendaraan');
            $table->timestamps();
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
