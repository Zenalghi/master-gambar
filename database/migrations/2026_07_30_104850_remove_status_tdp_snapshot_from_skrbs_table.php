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
            if (Schema::hasColumn('skrbs', 'status_tdp_snapshot')) {
                $table->dropColumn('status_tdp_snapshot');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skrbs', function (Blueprint $table) {
            if (!Schema::hasColumn('skrbs', 'status_tdp_snapshot')) {
                $table->string('status_tdp_snapshot')->nullable();
            }
        });
    }
};
