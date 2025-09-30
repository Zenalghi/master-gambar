<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HGambarOptional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class H_GambarOptionalController extends Controller
{
    /**
     * Menampilkan semua data Gambar Optional beserta silsilah lengkapnya.
     */
    public function index()
    {
        return HGambarOptional::with('varianBody.jenisKendaraan.typeChassis.merk.typeEngine')
            ->orderBy('id')
            ->get();
    }

    /**
     * Menyimpan data Gambar Optional baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'e_varian_body_id' => 'required|exists:e_varian_body,id|unique:h_gambar_optional,e_varian_body_id',
            'gambar_optional' => 'required|file|mimes:pdf',
            'deskripsi' => 'required|string|max:255',
        ]);

        $varianBody = \App\Models\EVarianBody::find($validated['e_varian_body_id']);

        // Buat path dan nama file yang deskriptif
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

        $gambarOptional = HGambarOptional::create([
            'e_varian_body_id' => $validated['e_varian_body_id'],
            'path_gambar_optional' => $path,
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        return response()->json($gambarOptional->load('varianBody'), 201);
    }

    /**
     * Memperbarui deskripsi Gambar Optional.
     */
    public function update(Request $request, HGambarOptional $hGambarOptional)
    {
        $validated = $request->validate([
            'deskripsi' => 'required|string|max:255',
        ]);

        $hGambarOptional->update([
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        return response()->json($hGambarOptional->fresh());
    }

    /**
     * Menghapus data Gambar Optional beserta filenya.
     */
    public function destroy(HGambarOptional $hGambarOptional)
    {
        // Hapus file fisik dari storage
        if (Storage::disk('master_gambar')->exists($hGambarOptional->path_gambar_optional)) {
            Storage::disk('master_gambar')->delete($hGambarOptional->path_gambar_optional);
        }

        $hGambarOptional->delete();

        return response()->noContent();
    }
}
