<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HGambarOptional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class H_GambarOptionalController extends Controller
{
    public function index()
    {
        return HGambarOptional::with('varianBody.jenisKendaraan.typeChassis.merk.typeEngine')
            ->orderBy('id')
            ->get();
    }

    // app/Http/Controllers/Api/H_GambarOptionalController.php

    public function store(Request $request)
    {
        $validated = $request->validate([
            'e_varian_body_id' => 'required|exists:e_varian_body,id',
            'gambar_optional' => 'required|file|mimes:pdf',
            'deskripsi' => 'required|string|max:255',
        ]);

        // 1. Ambil data Varian Body beserta semua relasi induknya
        $varianBody = \App\Models\EVarianBody::with('jenisKendaraan.typeChassis.merk.typeEngine')
            ->find($validated['e_varian_body_id']);

        // 2. Bangun path file
        $pathParts = [
            $varianBody->jenisKendaraan->typeChassis->merk->typeEngine->type_engine,
            $varianBody->jenisKendaraan->typeChassis->merk->merk,
            $varianBody->jenisKendaraan->typeChassis->type_chassis,
            $varianBody->jenisKendaraan->jenis_kendaraan,
            $varianBody->varian_body
        ];
        $basePath = implode('/', array_map(fn($part) => Str::slug($part), $pathParts));
        $fileName = Str::slug($validated['deskripsi']) . '.pdf';
        $path = $request->file('gambar_optional')->storeAs($basePath, $fileName, 'master_gambar');

        // 3. Buat entri baru dengan menyertakan SEMUA ID induk
        $gambarOptional = HGambarOptional::create([
            'a_type_engine_id' => $varianBody->jenisKendaraan->typeChassis->merk->typeEngine->id,
            'b_merk_id' => $varianBody->jenisKendaraan->typeChassis->merk->id,
            'c_type_chassis_id' => $varianBody->jenisKendaraan->typeChassis->id,
            'd_jenis_kendaraan_id' => $varianBody->jenisKendaraan->id,
            'e_varian_body_id' => $validated['e_varian_body_id'],
            'path_gambar_optional' => $path,
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        // Muat relasi untuk respons JSON
        return response()->json($gambarOptional->load('varianBody.jenisKendaraan.typeChassis.merk.typeEngine'), 201);
    }

    public function update(Request $request, HGambarOptional $gambarOptional) // <-- DIUBAH DI SINI
    {
        $validated = $request->validate([
            'deskripsi' => 'required|string|max:255',
        ]);

        // Lakukan update pada variabel yang benar
        $gambarOptional->update([ // <-- DIUBAH DI SINI
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        // Ambil kembali data berdasarkan ID dari variabel yang benar
        $updatedItem = HGambarOptional::with('varianBody.jenisKendaraan.typeChassis.merk.typeEngine')
            ->findOrFail($gambarOptional->id); // <-- DIUBAH DI SINI

        return response()->json($updatedItem);
    }

    public function destroy(HGambarOptional $gambarOptional) // <-- DIUBAH DI SINI
    {
        // Gunakan variabel yang benar untuk mengambil path
        if ($gambarOptional->path_gambar_optional && Storage::disk('master_gambar')->exists($gambarOptional->path_gambar_optional)) { // <-- DIUBAH DI SINI
            Storage::disk('master_gambar')->delete($gambarOptional->path_gambar_optional); // <-- DIUBAH DI SINI
        }

        // Hapus data dengan variabel yang benar
        $gambarOptional->delete(); // <-- DIUBAH DI SINI

        return response()->noContent();
    }
}
