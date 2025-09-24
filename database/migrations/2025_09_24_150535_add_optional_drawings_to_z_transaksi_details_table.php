<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            // Kolom ini akan menyimpan ID dari gambar optional/kelistrikan yang dipilih
            $table->foreignId('h_gambar_optional_id')->nullable()->after('pemeriksa_id')->constrained('h_gambar_optional')->nullOnDelete();
            $table->foreignId('i_gambar_kelistrikan_id')->nullable()->after('h_gambar_optional_id')->constrained('i_gambar_kelistrikan')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            $table->dropForeign(['h_gambar_optional_id']);
            $table->dropForeign(['i_gambar_kelistrikan_id']);
            $table->dropColumn(['h_gambar_optional_id', 'i_gambar_kelistrikan_id']);
        });
    }
};
