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
                'name' => 'Umardani',
                'username' => 'umar',
                'password' => Hash::make('qwerasdf'),
                'role_id' => 3,
                'signature' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'name' => 'Fatih',
                'username' => 'fatih',
                'password' => Hash::make('rekayasa'),
                'role_id' => 3,
                'signature' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pemeriksa',
                'username' => 'pemeriksa',
                'password' => Hash::make('rekayasa'),
                'role_id' => 2,
                'signature' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Drafter',
                'username' => 'drafter',
                'password' => Hash::make('rekayasa'),
                'role_id' => 3,
                'signature' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
