<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class A_TypeEngine extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('a_type_engines')->insert([
            ['id' => '01', 'type_engine' => 'EURO 2',
                'created_at' => now(),
                'updated_at' => now()
            ],
            ['id' => '02', 'type_engine' => 'EURO 4',
                'created_at' => now(),
                'updated_at' => now()
            ],

        ]);
    }
}
