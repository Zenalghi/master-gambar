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
                'id' => '010100101',
                'jenis_kendaraan' => 'BAK BESI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010100102',
                'jenis_kendaraan' => 'BAK KAYU',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010100103',
                'jenis_kendaraan' => 'BOX LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010100104',
                'jenis_kendaraan' => 'BOX NON LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010100105',
                'jenis_kendaraan' => 'REFRIGERATED BOX',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //Hino
            [
                'id' => '010200101',
                'jenis_kendaraan' => 'BAK BESI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010200102',
                'jenis_kendaraan' => 'BAK KAYU',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010200103',
                'jenis_kendaraan' => 'BOX LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010200104',
                'jenis_kendaraan' => 'BOX NON LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010200105',
                'jenis_kendaraan' => 'REFRIGERATED BOX',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //Isuzu
            [
                'id' => '010300101',
                'jenis_kendaraan' => 'BAK BESI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010300102',
                'jenis_kendaraan' => 'BAK KAYU',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010300103',
                'jenis_kendaraan' => 'BOX LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010300104',
                'jenis_kendaraan' => 'BOX NON LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010300105',
                'jenis_kendaraan' => 'REFRIGERATED BOX',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //UD Trucks
            [
                'id' => '010400101',
                'jenis_kendaraan' => 'BAK BESI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010400102',
                'jenis_kendaraan' => 'BAK KAYU',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010400103',
                'jenis_kendaraan' => 'BOX LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010400104',
                'jenis_kendaraan' => 'BOX NON LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010400105',
                'jenis_kendaraan' => 'REFRIGERATED BOX',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //Mercedes Benz
            [
                'id' => '010500101',
                'jenis_kendaraan' => 'BAK BESI',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010500102',
                'jenis_kendaraan' => 'BAK KAYU',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010500103',
                'jenis_kendaraan' => 'BOX LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010500104',
                'jenis_kendaraan' => 'BOX NON LOGAM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '010500105',
                'jenis_kendaraan' => 'REFRIGERATED BOX',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
