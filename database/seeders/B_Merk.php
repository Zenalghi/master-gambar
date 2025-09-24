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
                'id' => '0101',
                'merk' => 'MITSUBISHI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0102',
                'merk' => 'HINO',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0103',
                'merk' => 'ISUZU',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0104',
                'merk' => 'UDTRUCKS',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0105',
                'merk' => 'MERCEDES BENZ',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0106',
                'merk' => 'TATA',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0107',
                'merk' => 'FAW',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0108',
                'merk' => 'SUZUKI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0109',
                'merk' => 'DAIHATSU',
                'created_at' => now(),
                'updated_at' => now()
            ],

            // EURO 4 Merks
            [
                'id' => '0201',
                'merk' => 'MITSUBISHI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0202',
                'merk' => 'HINO',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0203',
                'merk' => 'ISUZU',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0204',
                'merk' => 'UDTRUCKS',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0205',
                'merk' => 'MERCEDES BENZ',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0206',
                'merk' => 'TATA',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0207',
                'merk' => 'FAW',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0208',
                'merk' => 'SUZUKI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0209',
                'merk' => 'DAIHATSU',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
