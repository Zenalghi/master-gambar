<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVarianBodyRequest;
use App\Http\Requests\UpdateVarianBodyRequest;
use App\Models\EVarianBody;
use App\Models\TransaksiVarian; // <-- Import
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException; // <-- Import

class E_VarianBodyController extends Controller
{
    /**
     * Mengambil semua varian body, diurutkan berdasarkan namanya,
     * dan memuat data induknya.
     */
    public function index()
    {
        return EVarianBody::with('jenisKendaraan.typeChassis.merk.typeEngine')
            ->orderBy('varian_body')
            ->get();
    }

    public function store(StoreVarianBodyRequest $request)
    {
        $varianBody = EVarianBody::create($request->validated());
        return response()->json($varianBody->load('jenisKendaraan.typeChassis.merk.typeEngine'), 201);
    }

    public function show(EVarianBody $varianBody)
    {
        return response()->json($varianBody->load('jenisKendaraan.typeChassis.merk.typeEngine'));
    }

    public function update(UpdateVarianBodyRequest $request, EVarianBody $varianBody)
    {
        $varianBody->update($request->validated());
        return response()->json($varianBody->fresh()->load('jenisKendaraan.typeChassis.merk.typeEngine'));
    }

    public function destroy(EVarianBody $varianBody)
    {
        // --- TAMBAHKAN PROTEKSI BARU ---
        if (TransaksiVarian::where('e_varian_body_id', $varianBody->id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Varian Body karena sudah digunakan dalam transaksi.']
            ]);
        }
        // -----------------------------

        // Logika hapus file-file terkait (sudah ada dan benar)
        $varianBody->load(['gambarUtama', 'gambarOptional']);
        // ... (sisa logika hapus file)

        $varianBody->delete();
        return response()->json(null, 204);
    }
}
