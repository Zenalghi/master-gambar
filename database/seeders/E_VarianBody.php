<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class E_VarianBody extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('e_varian_body')->insert([
            [
                'master_data_id' => 1,
                'varian_body' => 'FIX SIDE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'master_data_id' => 1,
                'varian_body' => 'DROP SIDE 3 WAY',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'master_data_id' => 1,
                'varian_body' => 'DROP SIDE 5 WAY',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'master_data_id' => 1,
                'varian_body' => 'DROP SIDE 7 WAY',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'master_data_id' => 1,
                'varian_body' => 'FIX SIDE + TERALIS RANGKA UNP',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'master_data_id' => 1,
                'varian_body' => 'FLAT DECK',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
