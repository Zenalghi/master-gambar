<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMerkRequest;
use App\Http\Requests\UpdateMerkRequest;
use App\Models\BMerk;
use App\Models\CTypeChassis;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class B_MerkController extends Controller
{
    /**
     * Menampilkan semua data, diurutkan berdasarkan nama merk A-Z.
     * Kita juga memuat relasi typeEngine agar data induknya ikut terbawa.
     */
    public function index()
    {
        return BMerk::with('typeEngine')->orderBy('merk')->get();
    }

    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreMerkRequest $request)
    {
        $validated = $request->validated();
        $typeEngineId = $validated['type_engine_id'];

        // --- LOGIKA ID OTOMATIS (4 DIGIT) ---
        // 1. Cari Merk terakhir yang memiliki type_engine_id yang sama.
        $lastMerk = BMerk::where('id', 'like', $typeEngineId . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextCode = '01'; // Default jika ini adalah merk pertama untuk type engine tsb.
        if ($lastMerk) {
            // 2. Ambil 2 digit terakhir dari ID, ubah ke integer, tambah 1.
            $lastCode = intval(substr($lastMerk->id, 2, 2));
            $nextCodeInt = $lastCode + 1;
            // 3. Format kembali menjadi 2 digit.
            $nextCode = str_pad($nextCodeInt, 2, '0', STR_PAD_LEFT);
        }

        // 4. Gabungkan untuk membuat ID komposit baru (contoh: '01' . '02' -> '0102').
        $newId = $typeEngineId . $nextCode;
        // ------------------------------------

        $merk = BMerk::create([
            'id' => $newId,
            'merk' => $validated['merk'],
        ]);

        // Muat relasi agar respons JSON berisi data typeEngine
        return response()->json($merk->load('typeEngine'), 201);
    }

    public function show(BMerk $merk)
    {
        return $merk->load('typeEngine');
    }

    public function update(UpdateMerkRequest $request, BMerk $merk)
    {
        $merk->update($request->validated());
        return response()->json($merk->fresh()->load('typeEngine'));
    }

    public function destroy(BMerk $merk)
    {
        if (CTypeChassis::where('id', 'like', $merk->id . '%')->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Merk karena masih memiliki data Tipe Chassis.']
            ]);
        }

        $merk->delete();
        return response()->json(null, 204);
    }
}
