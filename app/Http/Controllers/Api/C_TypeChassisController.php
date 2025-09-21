<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeChassisRequest;
use App\Http\Requests\UpdateTypeChassisRequest;
use App\Models\CTypeChassis;
use App\Models\DJenisKendaraan;

class C_TypeChassisController extends Controller
{
    public function index()
    {
        $chassis = CTypeChassis::with('merk')->orderBy('id')->get();
        return response()->json($chassis);
    }

    public function store(StoreTypeChassisRequest $request)
    {
        $compositeId = $request->merk_id . $request->chassis_code;
        $typeChassis = CTypeChassis::create([
            'id' => $compositeId,
            'type_chassis' => $request->type_chassis,
        ]);
        return response()->json($typeChassis, 201);
    }

    public function show(CTypeChassis $typeChassis)
    {
        return response()->json($typeChassis->load('merk'));
    }

    public function update(UpdateTypeChassisRequest $request, CTypeChassis $typeChassis)
    {
        // Menggunakan pola yang sama dengan JenisKendaraanController yang sudah berhasil
        $typeChassis->update($request->validated());
        // $typeChassis->refresh();
        return response()->json($typeChassis);
    }

    public function destroy(CTypeChassis $typeChassis)
    {
        if ($typeChassis->getJenisKendaraanChildren()->isNotEmpty()) {
            return response()->json(['message' => 'Tidak dapat menghapus Tipe Sasis karena masih memiliki data Jenis Kendaraan.'], 409);
        }
        $typeChassis->delete();
        return response()->json(null, 204);
    }
}