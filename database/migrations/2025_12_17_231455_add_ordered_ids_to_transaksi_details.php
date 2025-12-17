<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            // Kolom JSON untuk menyimpan urutan ID
            $table->json('ordered_independent_ids')->nullable()->after('data_gambar_utama');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(' z_transaksi_details', function (Blueprint $table) {
            //
        });
    }
};
