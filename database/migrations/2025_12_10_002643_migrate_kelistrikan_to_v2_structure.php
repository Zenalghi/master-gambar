<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
//2025_12_10_002643_migrate_kelistrikan_to_v2_structure
return new class extends Migration
{
    public function up(): void
    {
        // 1. Buat Tabel File Fisik (Struktur 3 ID sesuai Controller terbaru)
        Schema::create('master_kelistrikan_files', function (Blueprint $table) {
            $table->id();
            // Kita simpan 3 ID agar query independen lebih mudah
            $table->foreignId('a_type_engine_id')->constrained('a_type_engines')->onDelete('cascade');
            $table->foreignId('b_merk_id')->constrained('b_merks')->onDelete('cascade');
            $table->foreignId('c_type_chassis_id')->constrained('c_type_chassis')->onDelete('cascade');

            $table->string('path_file');
            $table->timestamps();

            // Unique constraint: 1 kombinasi Engine-Merk-Chassis hanya punya 1 file
            $table->unique(['a_type_engine_id', 'b_merk_id', 'c_type_chassis_id'], 'unique_file_combo');
        });

        // 2. MIGRASI DATA: Selamatkan data file dari tabel lama!
        $oldData = DB::table('i_gambar_kelistrikan')->get();

        foreach ($oldData as $data) {
            // Hanya pindahkan jika ada path filenya
            if (!empty($data->path_gambar_kelistrikan)) {
                // Cek agar tidak duplikat (safety check)
                $exists = DB::table('master_kelistrikan_files')
                    ->where('a_type_engine_id', $data->a_type_engine_id)
                    ->where('b_merk_id', $data->b_merk_id)
                    ->where('c_type_chassis_id', $data->c_type_chassis_id)
                    ->exists();

                if (!$exists) {
                    DB::table('master_kelistrikan_files')->insert([
                        'a_type_engine_id' => $data->a_type_engine_id,
                        'b_merk_id' => $data->b_merk_id,
                        'c_type_chassis_id' => $data->c_type_chassis_id,
                        'path_file' => $data->path_gambar_kelistrikan,
                        'created_at' => $data->created_at,
                        'updated_at' => $data->updated_at,
                    ]);
                }
            }
        }

        // 3. UBAH STRUKTUR TABEL LAMA (Logic Deskripsi)
        // Kita truncate dulu karena strukturnya berubah total (dari Chassis-based ke MasterData-based)
        // Tenang, data file fisiknya SUDAH AMAN di langkah no 2.
        DB::table('i_gambar_kelistrikan')->truncate();

        Schema::table('i_gambar_kelistrikan', function (Blueprint $table) {
            // Hapus constraint & kolom lama
            $table->dropForeign(['a_type_engine_id']);
            $table->dropForeign(['b_merk_id']);
            $table->dropForeign(['c_type_chassis_id']);

            // Hapus index unique lama (nama ini default dari migrasi awal Anda)
            $table->dropUnique('unique_kelistrikan_combo');

            $table->dropColumn(['a_type_engine_id', 'b_merk_id', 'c_type_chassis_id', 'path_gambar_kelistrikan']);

            // Tambah Kolom Baru untuk Logic
            $table->foreignId('master_data_id')->after('id')->constrained('master_data')->onDelete('cascade');
            $table->foreignId('master_kelistrikan_file_id')->after('master_data_id')->constrained('master_kelistrikan_files')->onDelete('cascade');

            // Unique: 1 Master Data = 1 Deskripsi Kelistrikan
            $table->unique('master_data_id', 'unique_desc_master_data');
        });
    }

    public function down(): void
    {
        // Rollback Logic (Jika perlu undo)

        // 1. Kembalikan struktur i_gambar_kelistrikan
        Schema::table('i_gambar_kelistrikan', function (Blueprint $table) {
            $table->dropForeign(['master_data_id']);
            $table->dropForeign(['master_kelistrikan_file_id']);
            $table->dropIndex('unique_desc_master_data');
            $table->dropColumn(['master_data_id', 'master_kelistrikan_file_id']);

            $table->foreignId('a_type_engine_id')->constrained('a_type_engines');
            $table->foreignId('b_merk_id')->constrained('b_merks');
            $table->foreignId('c_type_chassis_id')->constrained('c_type_chassis');
            $table->string('path_gambar_kelistrikan')->nullable();
        });

        // 2. Hapus tabel file baru
        Schema::dropIfExists('master_kelistrikan_files');
    }
};
