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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'e_varian_body_id' => 'required|exists:e_varian_body,id|unique:h_gambar_optional,e_varian_body_id',
            'gambar_optional' => 'required|file|mimes:pdf',
            'deskripsi' => 'required|string|max:255',
        ]);

        $varianBody = \App\Models\EVarianBody::find($validated['e_varian_body_id']);

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

        return response()->json($gambarOptional->load('varianBody.jenisKendaraan.typeChassis.merk.typeEngine'), 201);
    }

    public function update(Request $request, HGambarOptional $hGambarOptional)
    {
        $validated = $request->validate([
            'deskripsi' => 'required|string|max:255',
        ]);

        $hGambarOptional->update([
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        // --- INI PERBAIKANNYA ---
        // Muat kembali semua relasi yang dibutuhkan oleh model GambarOptional.fromJson di Flutter.
        $hGambarOptional->load('varianBody.jenisKendaraan.typeChassis.merk.typeEngine');

        return response()->json($hGambarOptional);
    }

    public function destroy(HGambarOptional $hGambarOptional)
    {
        if ($hGambarOptional->path_gambar_optional && Storage::disk('master_gambar')->exists($hGambarOptional->path_gambar_optional)) {
            Storage::disk('master_gambar')->delete($hGambarOptional->path_gambar_optional);
        }

        $hGambarOptional->delete();
        return response()->noContent();
    }
}
