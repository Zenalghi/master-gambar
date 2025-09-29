<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeChassisRequest;
use App\Http\Requests\UpdateTypeChassisRequest;
use App\Models\CTypeChassis;
use Illuminate\Validation\ValidationException;

class C_TypeChassisController extends Controller
{
    /**
     * Menampilkan semua data, diurutkan berdasarkan ID.
     * Memuat relasi merk dan typeEngine induknya.
     */
    public function index()
    {
        return CTypeChassis::with('merk.typeEngine')->orderBy('id')->get();
    }

    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreTypeChassisRequest $request)
    {
        $validated = $request->validated();
        $merkId = $validated['merk_id'];

        // --- LOGIKA ID OTOMATIS (7 DIGIT) ---
        $lastChassis = CTypeChassis::where('id', 'like', $merkId . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextCode = '001';
        if ($lastChassis) {
            $lastCode = intval(substr($lastChassis->id, 4, 3));
            $nextCodeInt = $lastCode + 1;
            $nextCode = str_pad($nextCodeInt, 3, '0', STR_PAD_LEFT);
        }

        $newId = $merkId . $nextCode;
        // ------------------------------------

        $typeChassis = CTypeChassis::create([
            'id' => $newId,
            'type_chassis' => $validated['type_chassis'],
        ]);

        return response()->json($typeChassis->load('merk.typeEngine'), 201);
    }

    public function show(CTypeChassis $typeChassis)
    {
        return response()->json($typeChassis->load('merk.typeEngine'));
    }

    public function update(UpdateTypeChassisRequest $request, CTypeChassis $typeChassis)
    {
        $typeChassis->update($request->validated());
        return response()->json($typeChassis->fresh()->load('merk.typeEngine'));
    }

    public function destroy(CTypeChassis $typeChassis)
    {
        if ($typeChassis->getJenisKendaraanChildren()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Tipe Chassis karena masih memiliki data Jenis Kendaraan.']
            ]);
        }
        $typeChassis->delete();
        return response()->json(null, 204);
    }
}
