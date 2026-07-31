<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skrb_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('skrb_id');
            $table->string('file_name'); // Nama ramah user saat unduh
            $table->string('storage_path'); // Alamat penyimpnan di disk 'skrb'
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->foreign('skrb_id')->references('id')->on('skrbs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skrb_histories');
    }
};
