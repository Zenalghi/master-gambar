<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'pemeriksa', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'drafter', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
