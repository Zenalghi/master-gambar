<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('i_gambar_kelistrikan', function (Blueprint $table) {
            $table->id();
            // Kunci asing ke c_type_chassis, yang merupakan string
            $table->string('c_type_chassis_id', 7);
            $table->string('path_gambar_kelistrikan');
            $table->timestamps();

            $table->foreign('c_type_chassis_id')
                ->references('id')
                ->on('c_type_chassis')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('i_gambar_kelistrikan');
    }
};
