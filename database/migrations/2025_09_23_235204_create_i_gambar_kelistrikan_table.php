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
            $table->string('a_type_engine_id', 2);
            $table->string('b_merk_id', 4);
            $table->string('c_type_chassis_id', 7);
            $table->string('path_gambar_kelistrikan');
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            // Mendefinisikan Foreign Key Constraints untuk integritas data
            $table->foreign('a_type_engine_id')->references('id')->on('a_type_engines')->onDelete('cascade');
            $table->foreign('b_merk_id')->references('id')->on('b_merks')->onDelete('cascade');
            $table->foreign('c_type_chassis_id')->references('id')->on('c_type_chassis')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('i_gambar_kelistrikan');
    }
};
