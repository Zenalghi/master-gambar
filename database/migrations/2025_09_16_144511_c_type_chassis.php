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
        Schema::create('c_type_chassis', function (Blueprint $table) {
            $table->id(); // <-- BERUBAH
            $table->string('type_chassis');
            $table->timestamps();
            $table->softDeletes(); // <-- TAMBAHKAN INI
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_type_chassis');
    }
};
