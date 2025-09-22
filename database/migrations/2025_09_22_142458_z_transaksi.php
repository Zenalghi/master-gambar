<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('z_transaksi', function (Blueprint $table) {
            $table->string('id', 14)->primary(); // Cth: 010100101-0001
            
            // --- KOLOM BARU UNTUK SILSILAH ---
            $table->string('a_type_engine_id', 2);
            $table->string('b_merk_id', 4);
            $table->string('c_type_chassis_id', 7);
            // --- AKHIR KOLOM BARU ---

            $table->string('d_jenis_kendaraan_id', 9);
            $table->foreignId('f_pengajuan_id')->constrained('f_pengajuan');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            // Definisikan foreign key untuk kolom baru
            $table->foreign('a_type_engine_id')->references('id')->on('a_type_engines');
            $table->foreign('b_merk_id')->references('id')->on('b_merks');
            $table->foreign('c_type_chassis_id')->references('id')->on('c_type_chassis');
            $table->foreign('d_jenis_kendaraan_id')->references('id')->on('d_jenis_kendaraan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_transaksi');
    }
};

