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
        // 1. Validasi: Sekarang kita menerima master_data_id
        $validated = $request->validate([
            'master_data_id' => 'required|integer|exists:master_data,id',
            'varian_body' => 'required|string|max:255', // <-- Terima nama varian body
            'gambar_utama' => 'required|file|mimes:pdf',
            'gambar_terurai' => 'required|file|mimes:pdf',
            'gambar_kontruksi' => 'required|file|mimes:pdf',
        ]);

        // 2. Buat atau ambil Varian Body
        // Ini memastikan Varian Body ada sebelum kita menggunakannya
        $varianBody = EVarianBody::firstOrCreate(
            [
                'master_data_id' => $validated['master_data_id'],
                'varian_body' => Str::upper($validated['varian_body']),
            ]
        );

        // 3. Bangun path folder (menggunakan helper baru)
        $basePath = $this->buildPath($varianBody);

        // 4. Bangun nama file (menggunakan helper baru)
        $fileNameUtama = $this->buildFileName($varianBody, 'Gambar Utama');
        $fileNameTerurai = $this->buildFileName($varianBody, 'Gambar Terurai');
        $fileNameKontruksi = $this->buildFileName($varianBody, 'Gambar Kontruksi');

        // 5. Simpan file-file
        $pathUtama = $request->file('gambar_utama')->storeAs($basePath, $fileNameUtama, 'master_gambar');
        $pathTerurai = $request->file('gambar_terurai')->storeAs($basePath, $fileNameTerurai, 'master_gambar');
        $pathKontruksi = $request->file('gambar_kontruksi')->storeAs($basePath, $fileNameKontruksi, 'master_gambar');

        // 6. Simpan data ke database
        $gambarUtama = GGambarUtama::updateOrCreate(
            ['e_varian_body_id' => $varianBody->id],
            [
                'path_gambar_utama' => $pathUtama,
                'path_gambar_terurai' => $pathTerurai,
                'path_gambar_kontruksi' => $pathKontruksi,
            ]
        );

        // Muat relasi baru untuk dikirim kembali sebagai konfirmasi
        $gambarUtama->load('varianBody.masterData');

        return response()->json($gambarUtama, 201);
    }

    /**
     * Helper function untuk membangun path folder baru.
     * Format: {id_master_data}/{nama_varian_body_slug}
     */
    private function buildPath(EVarianBody $varianBody): string
    {
        // Cukup gunakan ID Master Data dan nama Varian Body
        $masterDataId = $varianBody->master_data_id;
        $varianNameSlug = Str::slug($varianBody->varian_body);

        return $masterDataId . '/' . $varianNameSlug;
    }

    /**
     * Helper function untuk membangun nama file baru.
     * Format: {nama_varian_body_slug}_{suffix}.pdf
     */
    private function buildFileName(EVarianBody $varianBody, string $suffix): string
    {
        $varianNameSlug = Str::slug($varianBody->varian_body, '-');
        $suffixSlug = Str::slug($suffix, '-');

        return $varianNameSlug . '_' . $suffixSlug . '.pdf';
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
