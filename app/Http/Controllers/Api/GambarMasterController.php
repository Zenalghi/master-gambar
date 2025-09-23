<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use App\Models\GGambarUtama;
use App\Models\HGambarOptional;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GambarMasterController extends Controller
{
    /**
     * Menangani upload untuk 3 file Gambar Utama.
     */
    public function uploadGambarUtama(Request $request)
    {
        $request->validate([
            'e_varian_body_id' => 'required|exists:e_varian_body,id',
            'gambar_utama' => 'required|file|mimes:pdf',
            'gambar_terurai' => 'required|file|mimes:pdf',
            'gambar_kontruksi' => 'required|file|mimes:pdf',
        ]);

        $varianBody = EVarianBody::with('jenisKendaraan.typeChassis.merk.typeEngine')->find($request->e_varian_body_id);

        // 1. Bangun Path folder dinamis dari silsilah data
        $basePath = $this->buildPath($varianBody);

        // 2. Simpan setiap file dan dapatkan path-nya
        $pathUtama = $request->file('gambar_utama')->storeAs($basePath, $varianBody->varian_body . ' Gambar Utama.pdf', 'master_gambar');
        $pathTerurai = $request->file('gambar_terurai')->storeAs($basePath, $varianBody->varian_body . ' Gambar Terurai.pdf', 'master_gambar');
        $pathKontruksi = $request->file('gambar_kontruksi')->storeAs($basePath, $varianBody->varian_body . ' Gambar Kontruksi.pdf', 'master_gambar');

        // 3. Simpan path ke database (update jika sudah ada, buat jika belum)
        $gambarUtama = GGambarUtama::updateOrCreate(
            ['e_varian_body_id' => $varianBody->id],
            [
                'path_gambar_utama' => $pathUtama,
                'path_gambar_terurai' => $pathTerurai,
                'path_gambar_kontruksi' => $pathKontruksi,
            ]
        );

        return response()->json($gambarUtama, 201);
    }

    /**
     * Menangani upload untuk 1 file Gambar Optional.
     */
    public function uploadGambarOptional(Request $request)
    {
        $request->validate([
            'e_varian_body_id' => 'required|exists:e_varian_body,id',
            'gambar_optional' => 'required|file|mimes:pdf',
        ]);

        $varianBody = EVarianBody::with('jenisKendaraan.typeChassis.merk.typeEngine')->find($request->e_varian_body_id);
        $basePath = $this->buildPath($varianBody);

        $pathOptional = $request->file('gambar_optional')->storeAs($basePath, $varianBody->varian_body . ' Gambar Optional.pdf', 'master_gambar');

        $gambarOptional = HGambarOptional::updateOrCreate(
            ['e_varian_body_id' => $varianBody->id],
            ['path_gambar_optional' => $pathOptional]
        );

        return response()->json($gambarOptional, 201);
    }

    /**
     * Helper function untuk membangun path folder dinamis yang bersih.
     */
    private function buildPath(EVarianBody $varianBody): string
    {
        $engine = $varianBody->jenisKendaraan->typeChassis->merk->typeEngine->type_engine;
        $merk = $varianBody->jenisKendaraan->typeChassis->merk->merk;
        $chassis = $varianBody->jenisKendaraan->typeChassis->type_chassis;
        $jenis = $varianBody->jenisKendaraan->jenis_kendaraan;
        $varian = $varianBody->varian_body;

        // Membersihkan setiap bagian path dari karakter yang tidak valid untuk nama folder
        return Str::slug($engine) . '/' . Str::slug($merk) . '/' . Str::slug($chassis) . '/' . Str::slug($jenis) . '/' . Str::slug($varian);
    }
}

