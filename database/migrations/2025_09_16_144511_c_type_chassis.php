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
            $table->id();
            $table->foreignId('b_merk_id')->constrained('b_merks');
            $table->string('type_chassis');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['b_merk_id', 'type_chassis']);
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
