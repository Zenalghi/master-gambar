<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CTypeChassis;
use App\Models\EVarianBody;
use App\Models\GGambarUtama;
// use App\Models\HGambarOptional;
// use App\Models\IGambarKelistrikan;
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

    public function showPaths(GGambarUtama $gambarUtama)
    {
        return response()->json([
            'utama' => $gambarUtama->path_gambar_utama,
            'terurai' => $gambarUtama->path_gambar_terurai,
            'kontruksi' => $gambarUtama->path_gambar_kontruksi,
        ]);
    }

    /**
     * Mengirimkan konten file PDF dari disk 'master_gambar'
     * berdasarkan path yang diberikan di query parameter.
     */
    public function viewPdf(Request $request)
    {
        $validated = $request->validate([
            'path' => 'required|string',
        ]);

        $path = $validated['path'];

        // Cek keamanan dasar agar tidak bisa mengakses file di luar direktori
        if (Str::contains($path, '..')) {
            abort(403, 'Akses tidak diizinkan.');
        }

        if (!Storage::disk('master_gambar')->exists($path)) {
            return response()->json(['message' => 'File PDF tidak ditemukan.'], 404);
        }

        $filePath = Storage::disk('master_gambar')->path($path);

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
