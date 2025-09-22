<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('z_transaksi', function (Blueprint $table) {
            $table->string('id')->primary(); // Cth: 010100101-0001

            // Foreign keys ke tabel lain
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('d_jenis_kendaraan_id')->constrained('d_jenis_kendaraan');
            $table->foreignId('f_pengajuan_id')->constrained('f_pengajuan');
            $table->foreignId('user_id')->constrained('users'); // User yang membuat transaksi

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_transaksi');
    }
};
