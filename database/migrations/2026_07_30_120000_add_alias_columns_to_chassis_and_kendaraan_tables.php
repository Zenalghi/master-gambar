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
        Schema::table('c_type_chassis', function (Blueprint $table) {
            $table->string('jenis_tipe')->nullable()->after('type_chassis');
        });

        Schema::table('d_jenis_kendaraan', function (Blueprint $table) {
            $table->string('alias_kendaraan')->nullable()->after('jenis_kendaraan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('c_type_chassis', function (Blueprint $table) {
            $table->dropColumn('jenis_tipe');
        });

        Schema::table('d_jenis_kendaraan', function (Blueprint $table) {
            $table->dropColumn('alias_kendaraan');
        });
    }
};
