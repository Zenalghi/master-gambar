<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ATypeEngine;
use App\Models\BMerk;
use Illuminate\Http\Request;

class A_TypeEngineController extends Controller
{
    public function index()
    {
        return ATypeEngine::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string|size:2|unique:a_type_engines,id',
            'type_engine' => 'required|string|max:255',
        ]);

        $typeEngine = ATypeEngine::create($validated);
        return response()->json($typeEngine, 201);
    }

    public function show(ATypeEngine $typeEngine)
    {
        return $typeEngine;
    }

    public function update(Request $request, ATypeEngine $typeEngine)
    {
        $validated = $request->validate([
            'type_engine' => 'required|string|max:255',
        ]);
        
        $typeEngine->update($validated);
        return response()->json($typeEngine);
    }

    public function destroy(ATypeEngine $typeEngine)
    {
        // Proteksi: Cek apakah ada Merk yang menggunakan Tipe Engine ini
        if (BMerk::where('id', 'like', $typeEngine->id . '%')->exists()) {
            return response()->json(['message' => 'Tidak dapat menghapus Tipe Engine karena masih digunakan oleh data Merk.'], 409);
        }

        $typeEngine->delete();
        return response()->json(null, 204);
    }
}