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
            $table->string('sut_file')->nullable()->after('type_chassis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('c_type_chassis', function (Blueprint $table) {
            $table->dropColumn('sut_file');
        });
    }
};
