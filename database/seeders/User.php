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
                'name' => 'Fatih',
                'username' => 'fatih',
                'password' => Hash::make('rekayasa'),
                'role' => 'drafter',
            ],
        ]);
    }
}
