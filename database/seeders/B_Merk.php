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
            [
                'id' => '1',
                'merk' => 'MITSUBISHI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '2',
                'merk' => 'HINO',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '3',
                'merk' => 'ISUZU',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '4',
                'merk' => 'UDTRUCKS',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '5',
                'merk' => 'MERCEDES BENZ',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '6',
                'merk' => 'TATA',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '7',
                'merk' => 'FAW',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '8',
                'merk' => 'SUZUKI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '9',
                'merk' => 'DAIHATSU',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
