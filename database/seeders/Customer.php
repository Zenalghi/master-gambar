<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Customer extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('customers')->insert([
            [
                'nama_pt' => 'PT ANTIKA RAYA',
                'pj' => 'YANUAR',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'ADI JAYA MAKMUR',
                'pj' => 'KWAN PHA JIE',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'CV AMRI JAYA DINAMIKA',
                'pj' => 'KASMAN',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'CV ANUGERAH ARTHA KARYA',
                'pj' => 'SUJANTO',
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ... tambahkan data lain dari Excel Anda
        ]);
    }
}
