<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Urutan ini penting!
            A_TypeEngine::class,
            B_Merk::class,
            C_TypeChassis::class,
            D_JenisKendaraan::class,
            E_VarianBody::class,
            F_Pengajuan::class,
            Transaksi::class,

            // Seeder ini tidak memiliki dependensi, bisa ditaruh di akhir
            RoleSeeder::class,
            User::class,
            Customer::class,
        ]);
    }
}
