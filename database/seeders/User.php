<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class User extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'name' => 'Admin',
                'username' => 'admin', // Ganti dari email
                'password' => Hash::make('alhamdulillah'),
                'role' => 'admin', // Jadikan user pertama sebagai admin
            ],
            [
                'name' => 'Fatih',
                'username' => 'fatih', // Ganti dari email
                'password' => Hash::make('rekayasa'),
                'role' => 'drafter',
            ],
        ]);
    }
}
