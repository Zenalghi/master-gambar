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
        Schema::create('e_varian_body', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_data_id')->constrained('master_data')->onDelete('cascade');
            $table->string('varian_body');
            $table->timestamps();
            $table->softDeletes();

            // Mencegah nama varian body yang sama untuk master data yang sama
            $table->unique(['master_data_id', 'varian_body']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('e_varian_body');
    }
};
