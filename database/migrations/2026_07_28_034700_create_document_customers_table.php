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
        Schema::create('document_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();

            // Poin 1: Kop Surat (single PDF)
            $table->string('kop_surat_file')->nullable();

            // Poin 2: Data Umum Perusahaan (single PDF)
            $table->string('data_umum_file')->nullable();

            // Poin 3: TDP (multi PDF, max 20)
            $table->json('tdp_files')->nullable(); // [{path, uploaded_at}, ...]
            $table->date('tdp_masa_berlaku')->nullable();

            // Poin 4: Format Penomoran Permohonan (text fields)
            $table->string('permohonan_skrb')->nullable();
            $table->string('permohonan_rekom')->nullable();
            $table->string('alamat_permohonan')->nullable();
            $table->string('bidang_usaha')->nullable();
            $table->text('alamat_lengkap')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_customers');
    }
};
