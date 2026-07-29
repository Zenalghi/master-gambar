<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skrb_settings', function (Blueprint $table) {
            $table->id();
            $table->text('recipient_address');
            $table->timestamps();
        });

        // Masukkan data awal default jika tabel baru dibuat
        DB::table('skrb_settings')->insert([
            'recipient_address' => "Bapak Direktur Jendral Perhubungan Darat\nCq. Direktur Sarana dan Keselamatan\nTransportasi Jalan\nJl. Merdeka Barat No.8\nDi Jakarta",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('skrb_settings');
    }
};
