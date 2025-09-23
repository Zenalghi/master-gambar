<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CTypeChassis;
use App\Models\EVarianBody;
use App\Models\GGambarUtama;
use App\Models\HGambarOptional;
use App\Models\IGambarKelistrikan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class GambarMasterController extends Controller
{
    public function uploadGambarUtama(Request $request)
    {
        $request->validate([
            'e_varian_body_id' => 'required|exists:e_varian_body,id',
            'gambar_utama' => 'required|file|mimes:pdf',
            'gambar_terurai' => 'required|file|mimes:pdf',
            'gambar_kontruksi' => 'required|file|mimes:pdf',
        ]);

        $varianBody = EVarianBody::with('jenisKendaraan.typeChassis.merk.typeEngine')->find($request->e_varian_body_id);
        $basePath = $this->buildPath($varianBody);

        // --- Gunakan helper baru untuk membuat nama file dinamis ---
        $fileNameUtama = $this->buildFileName($varianBody, 'Gambar Utama');
        $fileNameTerurai = $this->buildFileName($varianBody, 'Gambar Terurai');
        $fileNameKontruksi = $this->buildFileName($varianBody, 'Gambar Kontruksi');

        $pathUtama = $request->file('gambar_utama')->storeAs($basePath, $fileNameUtama, 'master_gambar');
        $pathTerurai = $request->file('gambar_terurai')->storeAs($basePath, $fileNameTerurai, 'master_gambar');
        $pathKontruksi = $request->file('gambar_kontruksi')->storeAs($basePath, $fileNameKontruksi, 'master_gambar');

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

        $fileNameOptional = $this->buildFileName($varianBody, 'Gambar Optional');

        $pathOptional = $request->file('gambar_optional')->storeAs($basePath, $fileNameOptional, 'master_gambar');

        $gambarOptional = HGambarOptional::updateOrCreate(
            ['e_varian_body_id' => $varianBody->id],
            ['path_gambar_optional' => $pathOptional]
        );

        return response()->json($gambarOptional, 201);
    }

    public function uploadGambarKelistrikan(Request $request)
    {
        $request->validate([
            'c_type_chassis_id' => 'required|string|size:7|exists:c_type_chassis,id',
            'gambar_kelistrikan' => 'required|file|mimes:pdf',
        ]);

        $chassis = CTypeChassis::with('merk.typeEngine')->find($request->c_type_chassis_id);
        $basePath = $this->buildChassisPath($chassis);
        $fileName = Str::slug($chassis->type_chassis) . ' Gambar Kelistrikan.pdf';

        $path = $request->file('gambar_kelistrikan')->storeAs($basePath, $fileName, 'master_gambar');

        $gambarKelistrikan = IGambarKelistrikan::updateOrCreate(
            ['c_type_chassis_id' => $chassis->id],
            ['path_gambar_kelistrikan' => $path]
        );

        return response()->json($gambarKelistrikan, 201);
    }

    public function destroyGambarKelistrikan($c_type_chassis_id)
    {
        $gambarKelistrikan = IGambarKelistrikan::where('c_type_chassis_id', $c_type_chassis_id)->firstOrFail();
        Storage::disk('master_gambar')->delete($gambarKelistrikan->path_gambar_kelistrikan);
        $gambarKelistrikan->delete();
        return response()->json(null, 204);
    }

    /**
     * --- METHOD BARU ---
     * Menghapus data dan file fisik Gambar Utama berdasarkan ID Varian Body.
     */
    public function destroyGambarUtama($e_varian_body_id)
    {
        $gambarUtama = GGambarUtama::where('e_varian_body_id', $e_varian_body_id)->firstOrFail();

        // 1. Hapus file fisik dari storage disk 'master_gambar'
        Storage::disk('master_gambar')->delete([
            $gambarUtama->path_gambar_utama,
            $gambarUtama->path_gambar_terurai,
            $gambarUtama->path_gambar_kontruksi,
        ]);

        // 2. Hapus record dari database
        $gambarUtama->delete();

        return response()->json(null, 204); // 204 No Content
    }

    /**
     * --- METHOD BARU ---
     * Menghapus data dan file fisik Gambar Optional berdasarkan ID Varian Body.
     */
    public function destroyGambarOptional($e_varian_body_id)
    {
        $gambarOptional = HGambarOptional::where('e_varian_body_id', $e_varian_body_id)->firstOrFail();

        Storage::disk('master_gambar')->delete($gambarOptional->path_gambar_optional);
        $gambarOptional->delete();

        return response()->json(null, 204);
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

    private function buildFileName(EVarianBody $varianBody, string $suffix): string
    {
        $engine = $varianBody->jenisKendaraan->typeChassis->merk->typeEngine->type_engine;
        $merk = $varianBody->jenisKendaraan->typeChassis->merk->merk;
        $chassis = $varianBody->jenisKendaraan->typeChassis->type_chassis;
        $jenis = $varianBody->jenisKendaraan->jenis_kendaraan;
        $varian = $varianBody->varian_body;

        // Gabungkan semua nama dengan pemisah '-' dan tambahkan akhiran
        $baseName = collect([$engine, $merk, $chassis, $jenis, $varian, $suffix])
            ->map(fn($item) => Str::slug($item, '-')) // Bersihkan setiap bagian
            ->implode('_');

        return $baseName . '.pdf';
    }

    private function buildChassisPath(CTypeChassis $chassis): string
    {
        $engine = $chassis->merk->typeEngine->type_engine;
        $merk = $chassis->merk->merk;
        $chassisName = $chassis->type_chassis;

        return Str::slug($engine) . '/' . Str::slug($merk) . '/' . Str::slug($chassisName);
    }
}
