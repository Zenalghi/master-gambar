<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterData extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('master_data')->insert([
            // Data 1: Euro 2, Mitsubishi, Colt Diesel FE 71, Bak Besi
            [
                'a_type_engine_id' => 2,
                'b_merk_id' => 1,
                'c_type_chassis_id' => 1,
                'd_jenis_kendaraan_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Data 2: Euro 2, Mitsubishi, Colt Diesel FE 71, Bak Kayu
            [
                'a_type_engine_id' => 2,
                'b_merk_id' => 1,
                'c_type_chassis_id' => 1,
                'd_jenis_kendaraan_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Data 3: Euro 2, Mitsubishi, Colt Diesel FE 71, Box Logam
            [
                'a_type_engine_id' => 2,
                'b_merk_id' => 1,
                'c_type_chassis_id' => 1,
                'd_jenis_kendaraan_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Data 4: Euro 2, Mitsubishi, Colt Diesel FE 71, Box Non Logam
            [
                'a_type_engine_id' => 2,
                'b_merk_id' => 1,
                'c_type_chassis_id' => 1,
                'd_jenis_kendaraan_id' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Data 5: Euro 2, Mitsubishi, Colt Diesel FE 71, Refrigerated Box
            [
                'a_type_engine_id' => 2,
                'b_merk_id' => 1,
                'c_type_chassis_id' => 1,
                'd_jenis_kendaraan_id' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
