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
        Schema::create('a_type_engines', function (Blueprint $table) {
            $table->id(); // <-- BERUBAH: Menjadi auto-increment
            $table->string('type_engine');
            $table->timestamps();
            $table->softDeletes(); // <-- TAMBAHKAN INI
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_type_engines');
    }
};
