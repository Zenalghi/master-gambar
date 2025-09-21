<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMerkRequest;
use App\Http\Requests\UpdateMerkRequest;
use App\Models\BMerk;
use App\Models\CTypeChassis;

class B_MerkController extends Controller
{
    public function index()
    {
        return response()->json(BMerk::with('typeEngine')->get());
    }

    public function store(StoreMerkRequest $request)
    {
        // Gabungkan ID dari request untuk membuat ID komposit
        $compositeId = $request->type_engine_id . $request->merk_code;

        $merk = BMerk::create([
            'id' => $compositeId,
            'merk' => $request->merk,
        ]);

        return response()->json($merk, 201);
    }

    public function show(BMerk $merk)
    {
        return response()->json($merk);
    }

    public function update(UpdateMerkRequest $request, BMerk $merk)
    {
        // Hanya update nama merk
        $merk->update(['merk' => $request->merk]);
        return response()->json($merk);
    }

    public function destroy(BMerk $merk)
    {
        // Proteksi: Jangan hapus jika masih punya turunan (Tipe Sasis)
        if (CTypeChassis::where('id', 'like', $merk->id . '%')->exists()) {
            return response()->json(['message' => 'Tidak dapat menghapus Merk karena masih memiliki data Tipe Sasis.'], 409); // 409 Conflict
        }

        $merk->delete();
        return response()->json(null, 204);
    }
}