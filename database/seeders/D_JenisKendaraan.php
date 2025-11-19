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
                'id' => '1',
                'jenis_kendaraan' => 'BAK BESI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '2',
                'jenis_kendaraan' => 'BAK KAYU',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '3',
                'jenis_kendaraan' => 'BOX LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '4',
                'jenis_kendaraan' => 'BOX NON LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '5',
                'jenis_kendaraan' => 'REFRIGERATED BOX',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
