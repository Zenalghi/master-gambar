<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class F_Pengajuan extends Seeder
{
    public function run(): void
    {
        // Contoh: Menambahkan pilihan pengajuan untuk Varian Body dengan ID 1
        // DB::table('f_pengajuan')->insert([
        //     ['varian_body_id' => 1, 'jenis_pengajuan' => 'BARU'],
        //     ['varian_body_id' => 1, 'jenis_pengajuan' => 'VARIAN'],
        //     ['varian_body_id' => 2, 'jenis_pengajuan' => 'REVISI'],
        // ]);

        // Contoh: Menambahkan pilihan pengajuan untuk Varian Body tanpa foreign key
        DB::table('f_pengajuan')->insert([
            [
                'jenis_pengajuan' => 'BARU',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'jenis_pengajuan' => 'VARIAN',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'jenis_pengajuan' => 'REVISI',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
