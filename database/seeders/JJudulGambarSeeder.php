<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\JJudulGambar;

class JJudulGambarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $juduls = ['STANDAR', 'VARIAN 1', 'VARIAN 2', 'VARIAN 3'];

        foreach ($juduls as $judul) {
            JJudulGambar::create(['nama_judul' => $judul]);
        }
    }
}
