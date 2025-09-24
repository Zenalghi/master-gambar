<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class D_JenisKendaraan extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('d_jenis_kendaraan')->insert([
            [
                'id' => '010100101',
                'jenis_kendaraan' => 'BAK BESI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010100102',
                'jenis_kendaraan' => 'BAK KAYU',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010100103',
                'jenis_kendaraan' => 'BOX LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010100201',
                'jenis_kendaraan' => 'BAK BESI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            // ... tambahkan data lain dari Excel Anda
        ]);
    }
}
