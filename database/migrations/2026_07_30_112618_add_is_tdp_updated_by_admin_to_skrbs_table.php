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
        Schema::table('skrbs', function (Blueprint $table) {
            if (!Schema::hasColumn('skrbs', 'is_tdp_updated_by_admin')) {
                $table->boolean('is_tdp_updated_by_admin')->default(false)->after('nomor_urut');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skrbs', function (Blueprint $table) {
            if (Schema::hasColumn('skrbs', 'is_tdp_updated_by_admin')) {
                $table->dropColumn('is_tdp_updated_by_admin');
            }
        });
    }
};
