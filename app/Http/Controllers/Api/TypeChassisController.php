<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeChassisRequest;
use App\Http\Requests\UpdateTypeChassisRequest;
use App\Models\CTypeChassis;

class TypeChassisController extends Controller
{
    /**
     * Menampilkan semua data Tipe Sasis.
     */
    public function index()
    {
        // Eager load relasi 'merk' untuk data yang lebih kaya di frontend
        $chassis = CTypeChassis::with('merk')->orderBy('id')->get();
        return response()->json($chassis);
    }

    /**
     * Menyimpan Tipe Sasis baru.
     */
    public function store(StoreTypeChassisRequest $request)
    {
        // Buat ID komposit dari data yang sudah divalidasi
        $compositeId = $request->merk_id . $request->chassis_code;

        $typeChassis = CTypeChassis::create([
            'id' => $compositeId,
            'type_chassis' => $request->type_chassis,
        ]);

        return response()->json($typeChassis, 201);
    }

    /**
     * Menampilkan satu data Tipe Sasis.
     */
    public function show(CTypeChassis $typeChassis)
    {
        // Load relasi induknya saat menampilkan detail
        return response()->json($typeChassis->load('merk'));
    }

    /**
     * Memperbarui nama Tipe Sasis.
     */
    public function update(UpdateTypeChassisRequest $request, CTypeChassis $typeChassis)
    {
        $typeChassis->update($request->validated());
        return response()->json($typeChassis);
    }

    /**
     * Menghapus Tipe Sasis.
     */
    public function destroy(CTypeChassis $typeChassis)
    {
        // Gunakan method pembantu di model untuk mengecek apakah ada turunan
        if ($typeChassis->getJenisKendaraanChildren()->isNotEmpty()) {
            return response()->json(['message' => 'Tidak dapat menghapus Tipe Sasis karena masih memiliki data Jenis Kendaraan.'], 409); // 409 Conflict
        }

        $typeChassis->delete();
        return response()->json(null, 204);
    }
}