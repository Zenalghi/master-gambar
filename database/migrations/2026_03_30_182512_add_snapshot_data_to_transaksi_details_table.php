<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            // Kolom ini akan menyimpan foto (snapshot) alamat file PDF
            $table->json('snapshot_data')->nullable()->after('i_gambar_kelistrikan_id');
        });
    }

    public function down(): void
    {
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            $table->dropColumn('snapshot_data');
        });
    }
};