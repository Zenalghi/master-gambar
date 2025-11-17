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
        Schema::create('b_merks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('a_type_engine_id')->constrained('a_type_engines');
            $table->string('merk');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['a_type_engine_id', 'merk']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('b_merks');
    }
};
