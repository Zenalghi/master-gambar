<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\HGambarOptional;
use App\Models\EVarianBody;

return new class extends Migration
{
    public function up()
    {
        // 1. Tambah Kolom master_data_id dan Bikin e_varian_body_id jadi Nullable
        Schema::table('h_gambar_optional', function (Blueprint $table) {
            $table->foreignId('master_data_id')->nullable()->after('id')->constrained('master_data')->onDelete('cascade');

            // Ubah e_varian_body_id jadi nullable (karena independen gak pake ini lagi)
            $table->unsignedBigInteger('e_varian_body_id')->nullable()->change();
        });

        // 2. MIGRASI DATA (Script Otomatis Memindahkan Data Lama)
        $independents = HGambarOptional::where('tipe', 'independen')->get();

        foreach ($independents as $img) {
            if ($img->e_varian_body_id) {
                // Cari Tahu Master Data ID dari Varian Body lama
                $varian = EVarianBody::find($img->e_varian_body_id);

                if ($varian) {
                    // Pindahkan kepemilikan ke Master Data
                    $img->master_data_id = $varian->master_data_id;
                    $img->e_varian_body_id = null; // Putus hubungan dengan varian spesifik
                    $img->save();
                }
            }
        }
    }

    public function down()
    {
        Schema::table('h_gambar_optional', function (Blueprint $table) {
            $table->dropForeign(['master_data_id']);
            $table->dropColumn('master_data_id');
            // Mengembalikan e_varian_body_id jadi required mungkin sulit jika datanya sudah null, 
            // jadi biarkan nullable atau handle manual jika rollback.
        });
    }
};
