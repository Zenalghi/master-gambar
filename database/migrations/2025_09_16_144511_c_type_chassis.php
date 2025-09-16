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
            $table->string('id', 7)->primary(); // ID 7 digit (4 merk + 3 sasis), cth: '0101001'
            $table->string('type_chassis');
            $table->timestamps();
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
