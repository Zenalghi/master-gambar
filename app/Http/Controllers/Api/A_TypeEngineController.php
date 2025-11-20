<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ATypeEngine;
use App\Models\MasterData; // <-- Import MasterData untuk pengecekan
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class A_TypeEngineController extends Controller
{
    public function index()
    {
        return ATypeEngine::orderBy('type_engine')->get();
    }

    // --- FITUR BARU: List data yang dihapus ---
    public function trash()
    {
        return ATypeEngine::onlyTrashed()->orderBy('deleted_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type_engine' => [
                'required',
                'string',
                'max:255',
                Rule::unique('a_type_engines')->whereNull('deleted_at'),
            ],
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
            'type_engine' => [
                'required',
                'string',
                'max:255',
                Rule::unique('a_type_engines')->whereNull('deleted_at')->ignore($typeEngine->id),
            ],
        ]);

        $typeEngine->update($validated);
        return response()->json($typeEngine);
    }

    /**
     * Soft Delete (Hapus Sementara)
     * Tidak perlu cek relasi Merk/MasterData di sini karena cuma soft delete.
     */
    public function destroy(ATypeEngine $typeEngine)
    {
        $typeEngine->delete();
        return response()->json(null, 204);
    }

    /**
     * Restore (Kembalikan Data)
     */
    public function restore($id)
    {
        $typeEngine = ATypeEngine::onlyTrashed()->findOrFail($id);
        $typeEngine->restore();
        return response()->json($typeEngine);
    }

    /**
     * Force Delete (Hapus Permanen)
     * DI SINI kita cek relasi ke Master Data.
     */
    public function forceDelete($id)
    {
        // Cek apakah data ini dipakai di Master Data
        if (MasterData::where('a_type_engine_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Data tidak bisa dihapus permanen karena masih digunakan di Master Data.']
            ]);
        }

        $typeEngine = ATypeEngine::onlyTrashed()->findOrFail($id);
        $typeEngine->forceDelete();

        return response()->json(null, 204);
    }
}
