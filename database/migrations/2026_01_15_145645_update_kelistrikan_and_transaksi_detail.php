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
        // 1. MODIFIKASI TABEL KELISTRIKAN (Hati-hati urutannya!)
        Schema::table('i_gambar_kelistrikan', function (Blueprint $table) {
            // A. Lepas Foreign Key dulu (Agar index bisa dihapus)
            // Laravel otomatis mencari nama constraint: i_gambar_kelistrikan_master_data_id_foreign
            $table->dropForeign(['master_data_id']);

            // B. Baru Hapus Index Unik yang bermasalah
            $table->dropUnique('unique_desc_master_data');

            // C. Pasang lagi Foreign Key-nya (Penting!)
            $table->foreign('master_data_id')
                ->references('id')
                ->on('master_data')
                ->onDelete('cascade');

            // D. Tambahkan Index Biasa (Agar query tetap cepat, tapi bisa duplikat)
            $table->index('master_data_id');
        });

        // 2. MODIFIKASI TRANSAKSI DETAIL
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            $table->foreignId('i_gambar_kelistrikan_id')
                ->nullable()
                ->after('data_gambar_utama')
                ->constrained('i_gambar_kelistrikan')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Rollback Transaksi Detail
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            $table->dropForeign(['i_gambar_kelistrikan_id']);
            $table->dropColumn('i_gambar_kelistrikan_id');
        });

        // 2. Rollback Kelistrikan
        Schema::table('i_gambar_kelistrikan', function (Blueprint $table) {
            // Drop Index biasa & FK
            $table->dropForeign(['master_data_id']);
            $table->dropIndex(['master_data_id']);

            // Pasang lagi FK
            $table->foreign('master_data_id')
                ->references('id')
                ->on('master_data')
                ->onDelete('cascade');

            // Kembalikan ke mode strict (Unique Index)
            $table->unique('master_data_id', 'unique_desc_master_data');
        });
    }
};
