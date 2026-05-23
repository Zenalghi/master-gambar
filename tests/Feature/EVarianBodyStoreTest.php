<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EVarianBodyStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_varian_body_allows_same_name_on_different_master_data_and_reports_duplicates_cleanly()
    {
        $engineA = DB::table('a_type_engines')->insertGetId(['type_engine' => 'Engine A']);
        $merkA = DB::table('b_merks')->insertGetId(['merk' => 'Merk A']);
        $chassisA = DB::table('c_type_chassis')->insertGetId(['type_chassis' => 'Chassis A']);
        $jenisA = DB::table('d_jenis_kendaraan')->insertGetId(['jenis_kendaraan' => 'Jenis A']);

        $engineB = DB::table('a_type_engines')->insertGetId(['type_engine' => 'Engine B']);
        $merkB = DB::table('b_merks')->insertGetId(['merk' => 'Merk B']);
        $chassisB = DB::table('c_type_chassis')->insertGetId(['type_chassis' => 'Chassis B']);
        $jenisB = DB::table('d_jenis_kendaraan')->insertGetId(['jenis_kendaraan' => 'Jenis B']);

        $masterDataOne = DB::table('master_data')->insertGetId([
            'a_type_engine_id' => $engineA,
            'b_merk_id' => $merkA,
            'c_type_chassis_id' => $chassisA,
            'd_jenis_kendaraan_id' => $jenisA,
        ]);

        $masterDataTwo = DB::table('master_data')->insertGetId([
            'a_type_engine_id' => $engineB,
            'b_merk_id' => $merkB,
            'c_type_chassis_id' => $chassisB,
            'd_jenis_kendaraan_id' => $jenisB,
        ]);

        DB::table('e_varian_body')->insert([
            'master_data_id' => $masterDataOne,
            'varian_body' => 'PINTU BLK DOUBLE SWING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutMiddleware();

        $payload = [
            'master_data_id' => $masterDataOne,
            'varian_bodies' => [
                'PINTU BLK DOUBLE SWING',
                'pintu blk double swing',
                'PINTU BLK SINGLE',
            ],
        ];

        $firstResponse = $this->postJson('/api/varian-body', $payload);

        $firstResponse->assertStatus(201)
            ->assertJsonPath('created.0.varian_body', 'PINTU BLK SINGLE')
            ->assertJsonPath('skipped', ['PINTU BLK DOUBLE SWING'])
            ->assertJsonFragment([
                'message' => 'Beberapa varian berhasil disimpan, namun data berikut sudah ada: PINTU BLK DOUBLE SWING.',
            ]);

        $secondResponse = $this->postJson('/api/varian-body', [
            'master_data_id' => $masterDataTwo,
            'varian_bodies' => ['PINTU BLK DOUBLE SWING'],
        ]);

        $secondResponse->assertStatus(201)
            ->assertJsonPath('created.0.varian_body', 'PINTU BLK DOUBLE SWING')
            ->assertJsonPath('skipped', []);

        $this->assertDatabaseCount('e_varian_body', 3);
        $this->assertDatabaseHas('e_varian_body', [
            'master_data_id' => $masterDataOne,
            'varian_body' => 'PINTU BLK SINGLE',
        ]);
        $this->assertDatabaseHas('e_varian_body', [
            'master_data_id' => $masterDataTwo,
            'varian_body' => 'PINTU BLK DOUBLE SWING',
        ]);
    }
}
