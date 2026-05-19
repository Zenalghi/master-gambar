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
        Schema::table('customers', function (Blueprint $table) {
            // Kolom Drafter & Pemeriksa diletakkan di akhir tabel secara default (tanpa ->after)
            // Sangat aman dan tidak akan menggeser data existing.
            $table->string('nama_drafter')->nullable();
            $table->string('signature_drafter')->nullable();
            $table->string('nama_pemeriksa')->nullable();
            $table->string('signature_pemeriksa')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'nama_drafter',
                'signature_drafter',
                'nama_pemeriksa',
                'signature_pemeriksa'
            ]);
        });
    }
};
