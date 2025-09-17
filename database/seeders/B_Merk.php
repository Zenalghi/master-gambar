<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class B_Merk extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('b_merks')->insert([
            // EURO 2 Merks
            ['id' => '0101', 'merk' => 'MITSUBISHI'],
            ['id' => '0102', 'merk' => 'HINO'],
            ['id' => '0103', 'merk' => 'ISUZU'],
            // EURO 4 Merks
            ['id' => '0201', 'merk' => 'MITSUBISHI'],
            ['id' => '0202', 'merk' => 'HINO'],
            ['id' => '0203', 'merk' => 'ISUZU'],
            // ... tambahkan data lain dari Excel Anda
        ]);
    }
}
