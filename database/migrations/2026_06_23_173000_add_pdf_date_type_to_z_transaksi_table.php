<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('z_transaksi', function (Blueprint $table) {
            $table->string('pdf_date_type', 20)->default('today')->after('f_pengajuan_id');
        });
    }

    public function down(): void
    {
        Schema::table('z_transaksi', function (Blueprint $table) {
            $table->dropColumn('pdf_date_type');
        });
    }
};
