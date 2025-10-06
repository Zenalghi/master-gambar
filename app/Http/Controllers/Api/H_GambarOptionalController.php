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
            'tipe' => 'required|in:independen,dependen',
            'deskripsi' => 'required|string|max:255',
            'gambar_optional' => 'required|file|mimes:pdf',
            // Validasi kondisional: field ini wajib jika tipe-nya sesuai
            'e_varian_body_id' => 'required_if:tipe,independen|exists:e_varian_body,id',
            'g_gambar_utama_id' => 'required_if:tipe,dependen|exists:g_gambar_utama,id',
        ]);

        $tipe = $validated['tipe'];
        $pathData = []; // Untuk menyimpan data path
        $createData = [
            'tipe' => $tipe,
            'deskripsi' => Str::upper($validated['deskripsi']),
        ];

        if ($tipe === 'independen') {
            $varianBody = \App\Models\EVarianBody::with('jenisKendaraan.typeChassis.merk.typeEngine')
                ->find($validated['e_varian_body_id']);

            $pathData = [
                $varianBody->jenisKendaraan->typeChassis->merk->typeEngine->type_engine,
                $varianBody->jenisKendaraan->typeChassis->merk->merk,
                $varianBody->jenisKendaraan->typeChassis->type_chassis,
                $varianBody->jenisKendaraan->jenis_kendaraan,
                $varianBody->varian_body
            ];

            $createData += [
                'a_type_engine_id' => $varianBody->jenisKendaraan->typeChassis->merk->typeEngine->id,
                'b_merk_id' => $varianBody->jenisKendaraan->typeChassis->merk->id,
                'c_type_chassis_id' => $varianBody->jenisKendaraan->typeChassis->id,
                'd_jenis_kendaraan_id' => $varianBody->jenisKendaraan->id,
                'e_varian_body_id' => $validated['e_varian_body_id'],
            ];
        } else { // tipe === 'dependen'
            $gambarUtama = \App\Models\GGambarUtama::with('varianBody.jenisKendaraan.typeChassis.merk.typeEngine')
                ->find($validated['g_gambar_utama_id']);

            $pathData = [
                $gambarUtama->varianBody->jenisKendaraan->typeChassis->merk->typeEngine->type_engine,
                $gambarUtama->varianBody->jenisKendaraan->typeChassis->merk->merk,
                $gambarUtama->varianBody->jenisKendaraan->typeChassis->type_chassis,
                $gambarUtama->varianBody->jenisKendaraan->jenis_kendaraan,
                $gambarUtama->varianBody->varian_body,
                'dependen' // Tambahkan subfolder
            ];

            $createData['g_gambar_utama_id'] = $validated['g_gambar_utama_id'];
        }

        $basePath = implode('/', array_map(fn($part) => Str::slug($part), $pathData));
        $fileName = Str::slug($validated['deskripsi']) . '.pdf';
        $path = $request->file('gambar_optional')->storeAs($basePath, $fileName, 'master_gambar');
        $createData['path_gambar_optional'] = $path;

        $gambarOptional = HGambarOptional::create($createData);

        return response()->json($gambarOptional, 201);
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
