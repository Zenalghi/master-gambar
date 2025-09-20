<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class F_Pengajuan extends Seeder
{
    public function run(): void
    {
        // Contoh: Menambahkan pilihan pengajuan untuk Varian Body dengan ID 1
        DB::table('f_pengajuan')->insert([
            ['varian_body_id' => 1, 'jenis_pengajuan' => 'BARU'],
            ['varian_body_id' => 1, 'jenis_pengajuan' => 'VARIAN'],
            ['varian_body_id' => 1, 'jenis_pengajuan' => 'REVISI'],
        ]);
        
        // Contoh: Menambahkan pilihan pengajuan untuk Varian Body dengan ID 2
        DB::table('f_pengajuan')->insert([
            ['varian_body_id' => 2, 'jenis_pengajuan' => 'BARU'],
            ['varian_body_id' => 2, 'jenis_pengajuan' => 'VARIAN'],
            ['varian_body_id' => 2, 'jenis_pengajuan' => 'REVISI'],
        ]);
    }
}