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
        Schema::create('master_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('a_type_engine_id')->constrained('a_type_engines')->onDelete('restrict');
            $table->foreignId('b_merk_id')->constrained('b_merks')->onDelete('restrict');
            $table->foreignId('c_type_chassis_id')->constrained('c_type_chassis')->onDelete('restrict');
            $table->foreignId('d_jenis_kendaraan_id')->constrained('d_jenis_kendaraan')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            // Mencegah duplikasi kombinasi yang sama persis
            $table->unique([
                'a_type_engine_id',
                'b_merk_id',
                'c_type_chassis_id',
                'd_jenis_kendaraan_id'
            ], 'master_data_unique_combination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_data');
    }
};
