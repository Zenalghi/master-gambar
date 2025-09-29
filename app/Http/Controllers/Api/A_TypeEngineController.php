<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ATypeEngine;
use App\Models\BMerk;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class A_TypeEngineController extends Controller
{
    /**
     * Menampilkan semua data, diurutkan berdasarkan type_engine A-Z.
     */
    public function index()
    {
        return ATypeEngine::orderBy('type_engine')->get();
    }

    /**
     * Menyimpan data baru dengan ID otomatis.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type_engine' => 'required|string|max:255|unique:a_type_engines,type_engine',
        ]);

        // --- LOGIKA ID OTOMATIS ---
        // 1. Cari ID tertinggi yang sudah ada.
        $lastTypeEngine = ATypeEngine::orderBy('id', 'desc')->first();

        // 2. Tentukan ID berikutnya.
        $nextId = $lastTypeEngine ? intval($lastTypeEngine->id) + 1 : 1;

        // 3. Format menjadi 2 digit (misal: 1 -> "01", 10 -> "10").
        $newId = str_pad($nextId, 2, '0', STR_PAD_LEFT);
        // -------------------------

        $typeEngine = ATypeEngine::create([
            'id' => $newId,
            'type_engine' => $validated['type_engine'],
        ]);

        return response()->json($typeEngine, 201);
    }

    public function show(ATypeEngine $typeEngine)
    {
        return $typeEngine;
    }

    public function update(Request $request, ATypeEngine $typeEngine)
    {
        $validated = $request->validate([
            // Validasi unik, tapi abaikan data yang sedang diedit
            'type_engine' => 'required|string|max:255|unique:a_type_engines,type_engine,' . $typeEngine->id,
        ]);

        $typeEngine->update($validated);
        return response()->json($typeEngine);
    }

    public function destroy(ATypeEngine $typeEngine)
    {
        if (BMerk::where('id', 'like', $typeEngine->id . '%')->exists()) {
            // --- KIRIM PESAN ERROR DALAM FORMAT JSON YANG KONSISTEN ---
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Tipe Engine karena masih digunakan oleh data Merk.']
            ]);
        }

        $typeEngine->delete();
        return response()->json(null, 204);
    }
}
