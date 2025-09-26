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
                'name' => 'Deni',
                'username' => 'deni',
                'password' => Hash::make('qweqweqwe'),
                'role_id' => 1,
                'signature' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'name' => 'Fatih',
                'username' => 'fatih',
                'password' => Hash::make('asdasdasd'),
                'role_id' => 3,
                'signature' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Umardani',
                'username' => 'umar',
                'password' => Hash::make('zxczxczxc'),
                'role_id' => 2,
                'signature' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => Hash::make('qweqweqwe'),
                'role_id' => 1,
                'signature' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
