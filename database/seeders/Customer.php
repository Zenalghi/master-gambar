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
            ['nama_pt' => 'ADI JAYA MAKMUR', 'pj' => 'KWAN PHA JIE'],
            ['nama_pt' => 'CV AMRI JAYA DINAMIKA', 'pj' => 'KASMAN'],
            ['nama_pt' => 'CV ANUGERAH ARTHA KARYA', 'pj' => 'SUJANTO'],
            // ... tambahkan data lain dari Excel Anda
        ]);
    }
}
