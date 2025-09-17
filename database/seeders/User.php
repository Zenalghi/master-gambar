<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class User extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'name' => 'ASEP',
                'email' => 'asep@example.com',
                'password' => Hash::make('1234qwer'), // Ganti 'password' dengan password default yg aman
                'role' => 'user',
            ],
            [
                'name' => 'HASAN',
                'email' => 'hasan@example.com',
                'password' => Hash::make('qwer1234'),
                'role' => 'user',
            ],
            // ... tambahkan data lain dari Excel Anda
        ]);
    }
}
