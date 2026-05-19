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
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            // Pihak penyetujuan, diletakkan di akhir tabel.
            $table->string('pihak_penyetujuan')->default('vendor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            $table->dropColumn('pihak_penyetujuan');
        });
    }
};
