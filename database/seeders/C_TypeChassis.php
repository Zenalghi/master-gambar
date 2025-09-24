<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class C_TypeChassis extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('c_type_chassis')->insert([
            [
                'id' => '0101001',
                'type_chassis' => 'COLT DIESEL FE 71 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0101002',
                'type_chassis' => 'COLT DIESEL FE 71 PS (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0101003',
                'type_chassis' => 'COLT DIESEL FE 71 L (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0102010',
                'type_chassis' => 'FG8JP1A-AGJ (FG 215 JP)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            // ... tambahkan data lain dari Excel Anda
        ]);
    }
}
