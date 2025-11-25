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
            [
                'nama_pt' => 'CV AUDI ERSA UTAMA',
                'pj' => 'SITI YUNIA',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'CV BAGUS JAYA',
                'pj' => 'OKY',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'CV BERDIKARI JAYA',
                'pj' => 'EFRANDY RACHMAN',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'CV BERKAH RAMA',
                'pj' => 'H.MOCH.SUHENDI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'CV BINA TEHNIK',
                'pj' => 'HARRY SANTO',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'CV BINTANG PRIMA PERKASA',
                'pj' => 'AGUS SETIAWAN HIDAYAT, SE',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'CV BINTANG SELATAN MOTOR',
                'pj' => 'SOFIAN KOLLENG',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'CV BUARAN MOTOR',
                'pj' => 'TAN WIJAYA',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'CV CENTRAL LABA-LABA MOTOR',
                'pj' => 'ZIKRI NUR ACHMAD',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama_pt' => 'CV CENTRAL NUSANTARA PERSADA',
                'pj' => 'CHANDRA M. SEPTIAN',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
