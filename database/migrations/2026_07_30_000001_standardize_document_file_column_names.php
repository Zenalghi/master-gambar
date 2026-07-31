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
        if (Schema::hasTable('document_customers')) {
            Schema::table('document_customers', function (Blueprint $table) {
                if (Schema::hasColumn('document_customers', 'kop_surat') && !Schema::hasColumn('document_customers', 'kop_surat_file')) {
                    $table->renameColumn('kop_surat', 'kop_surat_file');
                }
                if (Schema::hasColumn('document_customers', 'data_umum') && !Schema::hasColumn('document_customers', 'data_umum_file')) {
                    $table->renameColumn('data_umum', 'data_umum_file');
                }
            });
        }

        if (Schema::hasTable('c_type_chassis')) {
            Schema::table('c_type_chassis', function (Blueprint $table) {
                if (Schema::hasColumn('c_type_chassis', 'sut_pdf_path') && !Schema::hasColumn('c_type_chassis', 'sut_file')) {
                    $table->renameColumn('sut_pdf_path', 'sut_file');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('document_customers')) {
            Schema::table('document_customers', function (Blueprint $table) {
                if (Schema::hasColumn('document_customers', 'kop_surat_file')) {
                    $table->renameColumn('kop_surat_file', 'kop_surat');
                }
                if (Schema::hasColumn('document_customers', 'data_umum_file')) {
                    $table->renameColumn('data_umum_file', 'data_umum');
                }
            });
        }

        if (Schema::hasTable('c_type_chassis')) {
            Schema::table('c_type_chassis', function (Blueprint $table) {
                if (Schema::hasColumn('c_type_chassis', 'sut_file')) {
                    $table->renameColumn('sut_file', 'sut_pdf_path');
                }
            });
        }
    }
};
