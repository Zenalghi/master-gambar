<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ATypeEngine;
use App\Models\BMerk;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

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
            'type_engine' => [ // <-- Ubah menjadi array
                'required',
                'string',
                'max:255',
                // Cek unik hanya pada data yang tidak di-soft-delete
                Rule::unique('a_type_engines')->whereNull('deleted_at'),
            ],
        ]);

        // --- HAPUS SEMUA LOGIKA ID OTOMATIS ---
        $typeEngine = ATypeEngine::create($validated);

        return response()->json($typeEngine, 201);
    }

    public function show(ATypeEngine $typeEngine)
    {
        return $typeEngine;
    }

    /**
     * Memperbarui data.
     */
    public function update(Request $request, ATypeEngine $typeEngine)
    {
        $validated = $request->validate([
            'type_engine' => [ // <-- Ubah menjadi array
                'required',
                'string',
                'max:255',
                // Cek unik, abaikan ID saat ini dan yang sudah di-soft-delete
                Rule::unique('a_type_engines')->whereNull('deleted_at')->ignore($typeEngine->id),
            ],
        ]);

        $typeEngine->update($validated);
        return response()->json($typeEngine);
    }

    /**
     * Menghapus data (sekarang menggunakan SoftDeletes).
     */
    public function destroy(ATypeEngine $typeEngine)
    {
        // Cek relasi ke B_Merk (sekarang cek berdasarkan foreign key integer)
        if (BMerk::where('a_type_engine_id', $typeEngine->id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Tipe Engine karena masih digunakan oleh data Merk.']
            ]);
        }

        $typeEngine->delete(); // Ini akan melakukan soft delete
        return response()->json(null, 204);
    }
}
