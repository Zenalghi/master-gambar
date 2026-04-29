<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            // Tambahkan kolom desc_space (default 0 baris)
            $table->integer('desc_space')->default(0)->after('deskripsi_optional');
        });
    }

    public function down(): void
    {
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            $table->dropColumn('desc_space');
        });
    }
};
