<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJenisKendaraanRequest;
use App\Http\Requests\UpdateJenisKendaraanRequest;
use App\Models\DJenisKendaraan;

class JenisKendaraanController extends Controller
{
    public function index()
    {
        $jenisKendaraan = DJenisKendaraan::with('typeChassis.merk')->orderBy('id')->get();
        return response()->json($jenisKendaraan);
    }

    public function store(StoreJenisKendaraanRequest $request)
    {
        // Buat ID komposit dari data yang sudah divalidasi
        $compositeId = $request->type_chassis_id . $request->jenis_kendaraan_code;

        $jenisKendaraan = DJenisKendaraan::create([
            'id' => $compositeId,
            'jenis_kendaraan' => $request->jenis_kendaraan,
        ]);

        return response()->json($jenisKendaraan, 201);
    }

    public function show(DJenisKendaraan $jenisKendaraan)
    {
        return response()->json($jenisKendaraan->load('typeChassis.merk'));
    }

    public function update(UpdateJenisKendaraanRequest $request, DJenisKendaraan $jenisKendaraan)
    {
        $jenisKendaraan->update($request->validated());
        return response()->json($jenisKendaraan);
    }

    public function destroy(DJenisKendaraan $jenisKendaraan)
    {
        // Proteksi: Cek apakah memiliki turunan (Varian Body) menggunakan relasi Eloquent
        if ($jenisKendaraan->varianBody()->exists()) {
            return response()->json(['message' => 'Tidak dapat menghapus Jenis Kendaraan karena masih memiliki data Varian Body.'], 409);
        }

        $jenisKendaraan->delete();
        return response()->json(null, 204);
    }
}