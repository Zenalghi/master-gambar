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
            // Kolom Drafter dari sisi Customer
            $table->string('nama_drafter')->nullable()->after('signature_pj');
            $table->string('signature_drafter')->nullable()->after('nama_drafter');

            // Kolom Pemeriksa dari sisi Customer
            $table->string('nama_pemeriksa')->nullable()->after('signature_drafter');
            $table->string('signature_pemeriksa')->nullable()->after('nama_pemeriksa');
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
