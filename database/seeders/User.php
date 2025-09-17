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
                'password' => Hash::make('abcd'), // Ganti 'password' dengan password default yg aman
                'role' => 'drafter',
            ],
            [
                'name' => 'HASAN',
                'email' => 'hasan@example.com',
                'password' => Hash::make('abcd'),
                'role' => 'drafter',
            ],
            // ... tambahkan data lain dari Excel Anda
        ]);
    }
}
