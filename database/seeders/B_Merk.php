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
            ['id' => '0104', 'merk' => 'UDTRUCKS'],
            ['id' => '0105', 'merk' => 'MERCEDES BENZ'],
            ['id' => '0106', 'merk' => 'TATA'],
            ['id' => '0107', 'merk' => 'FAW'],
            ['id' => '0108', 'merk' => 'SUZUKI'],
            ['id' => '0109', 'merk' => 'DAIHATSU'],

            // EURO 4 Merks
            ['id' => '0201', 'merk' => 'MITSUBISHI'],
            ['id' => '0202', 'merk' => 'HINO'],
            ['id' => '0203', 'merk' => 'ISUZU'],
            ['id' => '0204', 'merk' => 'UDTRUCKS'],
            ['id' => '0205', 'merk' => 'MERCEDES BENZ'],
            ['id' => '0206', 'merk' => 'TATA'],
            ['id' => '0207', 'merk' => 'FAW'],
            ['id' => '0208', 'merk' => 'SUZUKI'],
            ['id' => '0209', 'merk' => 'DAIHATSU'],
        ]);
    }
}
