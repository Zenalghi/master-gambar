<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('z_transaksi', function (Blueprint $table) {
            // ID baru dengan format 'mmyy-xxxx' (misal: '1125-0001')
            $table->string('id', 9)->primary();

            // Relasi ke tabel-tabel baru
            $table->foreignId('master_data_id')->constrained('master_data')->onDelete('restrict');
            $table->foreignId('f_pengajuan_id')->constrained('f_pengajuan');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('user_id')->constrained('users');

            $table->timestamps();
            $table->softDeletes(); // Tambahkan Soft Deletes
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_transaksi');
    }
};
