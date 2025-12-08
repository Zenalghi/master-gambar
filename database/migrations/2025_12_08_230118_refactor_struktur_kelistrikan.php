<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Buat Tabel Baru: master_kelistrikan_files
        // Tabel ini khusus menyimpan Path File PDF agar 1 chassis = 1 file
        Schema::create('master_kelistrikan_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('c_type_chassis_id')->constrained('c_type_chassis')->onDelete('cascade');
            $table->string('path_file');
            $table->timestamps();
            // Pastikan 1 chassis hanya punya 1 file fisik
            $table->unique('c_type_chassis_id', 'unique_file_chassis');
        });

        // 2. MIGRASI DATA: Pindahkan data file yang ada ke tabel baru
        // Kita ambil data unik berdasarkan chassis dari tabel lama
        $oldData = DB::table('i_gambar_kelistrikan')->get();
        foreach ($oldData as $data) {
            // Cek apakah file fisik untuk chassis ini sudah dicatat di tabel baru
            $exists = DB::table('master_kelistrikan_files')
                ->where('c_type_chassis_id', $data->c_type_chassis_id)
                ->exists();

            if (!$exists && !empty($data->path_gambar_kelistrikan)) {
                DB::table('master_kelistrikan_files')->insert([
                    'c_type_chassis_id' => $data->c_type_chassis_id,
                    'path_file' => $data->path_gambar_kelistrikan,
                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at,
                ]);
            }
        }

        // 3. BERSIHKAN TABEL LAMA (Logic Reset)
        // Karena struktur berubah total (dari Chassis -> MasterData), 
        // data deskripsi lama tidak bisa otomatis dipetakan. 
        // Kita truncate (kosongkan) tabel logic, tapi FILE FISIK SUDAH AMAN di tabel baru.
        DB::table('i_gambar_kelistrikan')->truncate();

        // 4. UBAH STRUKTUR TABEL LAMA
        Schema::table('i_gambar_kelistrikan', function (Blueprint $table) {
            // Hapus constraint lama
            // Nama foreign key biasanya: nama_tabel_nama_kolom_foreign
            $table->dropForeign(['a_type_engine_id']);
            $table->dropForeign(['b_merk_id']);
            $table->dropForeign(['c_type_chassis_id']);

            // Hapus index unique lama
            $table->dropUnique('unique_kelistrikan_combo');

            // Hapus kolom lama
            $table->dropColumn(['a_type_engine_id', 'b_merk_id', 'c_type_chassis_id', 'path_gambar_kelistrikan']);

            // Tambah kolom relasi baru
            $table->foreignId('master_data_id')->after('id')->constrained('master_data')->onDelete('cascade');
            $table->foreignId('master_kelistrikan_file_id')->after('master_data_id')->constrained('master_kelistrikan_files')->onDelete('cascade');

            // Buat unique baru: 1 Master Data hanya punya 1 Deskripsi Kelistrikan
            $table->unique('master_data_id', 'unique_desc_master_data');
        });
    }

    public function down(): void
    {
        // Logic rollback (jika perlu undo)
        // Ini agak rumit karena data sudah berubah bentuk, 
        // biasanya di production kita jarang rollback struktur besar, tapi restore backup.
        Schema::dropIfExists('master_kelistrikan_files');

        // Kembalikan struktur lama (kosong)
        Schema::table('i_gambar_kelistrikan', function (Blueprint $table) {
            $table->dropForeign(['master_data_id']);
            $table->dropForeign(['master_kelistrikan_file_id']);
            $table->dropColumn(['master_data_id', 'master_kelistrikan_file_id']);

            $table->foreignId('a_type_engine_id')->constrained('a_type_engines');
            $table->foreignId('b_merk_id')->constrained('b_merks');
            $table->foreignId('c_type_chassis_id')->constrained('c_type_chassis');
            $table->string('path_gambar_kelistrikan')->nullable();
        });
    }
};
