<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJenisKendaraanRequest;
use App\Http\Requests\UpdateJenisKendaraanRequest;
use App\Models\DJenisKendaraan;
use Illuminate\Validation\ValidationException;

class D_JenisKendaraanController extends Controller
{
    /**
     * Menampilkan semua data, diurutkan berdasarkan nama jenis kendaraan.
     */
    public function index()
    {
        return DJenisKendaraan::with('typeChassis.merk.typeEngine')->orderBy('jenis_kendaraan')->get();
    }

    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreJenisKendaraanRequest $request)
    {
        $validated = $request->validated();
        $typeChassisId = $validated['type_chassis_id'];

        // --- LOGIKA ID OTOMATIS (9 DIGIT) ---
        $lastJenis = DJenisKendaraan::where('id', 'like', $typeChassisId . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextCode = 'AA'; // Default jika ini adalah jenis pertama
        if ($lastJenis) {
            $lastCode = substr($lastJenis->id, 7, 2); // Ambil 2 karakter terakhir
            $nextCode = ++$lastCode; // Increment karakter (e.g., 'AA' -> 'AB')
        }

        $newId = $typeChassisId . $nextCode;
        // ------------------------------------

        $jenisKendaraan = DJenisKendaraan::create([
            'id' => $newId,
            'jenis_kendaraan' => $validated['jenis_kendaraan'],
        ]);

        return response()->json($jenisKendaraan->load('typeChassis.merk.typeEngine'), 201);
    }

    public function show(DJenisKendaraan $jenisKendaraan)
    {
        return response()->json($jenisKendaraan->load('typeChassis.merk.typeEngine'));
    }

    public function update(UpdateJenisKendaraanRequest $request, DJenisKendaraan $jenisKendaraan)
    {
        $jenisKendaraan->update($request->validated());
        return response()->json($jenisKendaraan->fresh()->load('typeChassis.merk.typeEngine'));
    }

    public function destroy(DJenisKendaraan $jenisKendaraan)
    {
        if ($jenisKendaraan->varianBody()->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Jenis Kendaraan karena masih memiliki data Varian Body.']
            ]);
        }

        $jenisKendaraan->delete();
        return response()->json(null, 204);
    }
}
