<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Transaksi extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('z_transaksi')->insert([
            [
                'id' => '010100101-0001', // ID Jenis Kendaraan + Counter
                'a_type_engine_id' => '01', 
                'b_merk_id' => '0101', 
                'c_type_chassis_id' => '0101001', 
                'd_jenis_kendaraan_id' => '010100101', // BAK BESI
                'customer_id' => 1, // ADI JAYA MAKMUR
                'f_pengajuan_id' => 1, // BARU
                'user_id' => 4, // Ridho Al
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '010100103-0001', // ID Jenis Kendaraan + Counter
                'a_type_engine_id' => '01',
                'b_merk_id' => '0101',
                'c_type_chassis_id' => '0101001',
                'd_jenis_kendaraan_id' => '010100103', // BOX LOGAM
                'customer_id' => 2, // CV AMRI JAYA DINAMIKA
                'f_pengajuan_id' => 2, // VARIAN
                'user_id' => 3, // Fahri Nur
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '010100102-0001', // ID Jenis Kendaraan + Counter
                'a_type_engine_id' => '01',
                'b_merk_id' => '0101',
                'c_type_chassis_id' => '0101001',
                'd_jenis_kendaraan_id' => '010100102', // BAK KAYU
                'customer_id' => 3, // CV ANUGERAH ARTHA KARYA
                'f_pengajuan_id' => 3, // REVISI
                'user_id' => 2, // Fatih
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
