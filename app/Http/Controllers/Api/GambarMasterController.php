<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use App\Models\GGambarUtama;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class GambarMasterController extends Controller
{
    public function uploadGambarUtama(Request $request)
    {
        // 1. Validasi (tidak berubah)
        $validated = $request->validate([
            'master_data_id' => 'required|integer|exists:master_data,id',
            'varian_body' => 'required|string|max:255',
            'gambar_utama' => 'required|file|mimes:pdf',
            'gambar_terurai' => 'required|file|mimes:pdf',
            'gambar_kontruksi' => 'required|file|mimes:pdf',
        ]);

        // 2. Buat atau ambil Varian Body (tidak berubah)
        $varianBody = EVarianBody::firstOrCreate(
            [
                'master_data_id' => $validated['master_data_id'],
                'varian_body' => Str::upper($validated['varian_body']),
            ]
        );
        // Pada titik ini, $varianBody dijamin memiliki ID yang permanen
        // (misal: 45)

        // 3. Bangun path folder BARU (menggunakan ID, bukan nama)
        // Hasilnya akan seperti: "12/45"
        $basePath = $this->buildPath($varianBody);

        // 4. Bangun nama file BARU (hanya berdasarkan suffix)
        // Hasilnya akan seperti: "gambar-utama.pdf"
        $fileNameUtama = $this->buildFileName('Gambar Utama');
        $fileNameTerurai = $this->buildFileName('Gambar Terurai');
        $fileNameKontruksi = $this->buildFileName('Gambar Kontruksi');

        // 5. Simpan file-file (logika sama, path & nama file baru)
        // Path final cth: "12/45/gambar-utama.pdf"
        $pathUtama = $request->file('gambar_utama')->storeAs($basePath, $fileNameUtama, 'master_gambar');
        $pathTerurai = $request->file('gambar_terurai')->storeAs($basePath, $fileNameTerurai, 'master_gambar');
        $pathKontruksi = $request->file('gambar_kontruksi')->storeAs($basePath, $fileNameKontruksi, 'master_gambar');

        // 6. Simpan data ke database (logika sama)
        $gambarUtama = GGambarUtama::updateOrCreate(
            ['e_varian_body_id' => $varianBody->id],
            [
                'path_gambar_utama' => $pathUtama,
                'path_gambar_terurai' => $pathTerurai,
                'path_gambar_kontruksi' => $pathKontruksi,
            ]
        );

        $gambarUtama->load('varianBody.masterData');
        return response()->json($gambarUtama, 201);
    }

    /**
     * Helper function untuk membangun path folder baru yang stabil.
     * Format: {id_master_data}/{id_varian_body}
     */
    private function buildPath(EVarianBody $varianBody): string
    {
        return $varianBody->master_data_id . '/' . $varianBody->id;
    }

    /**
     * Helper function untuk membangun nama file baru yang stabil.
     * Format: {suffix_slug}.pdf
     */
    private function buildFileName(string $suffix): string
    {
        return Str::slug($suffix, '-') . '.pdf';
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
        return response()->json(null, 204);
    }

    public function showPaths(GGambarUtama $gambarUtama)
    {
        // 1. Load relasi gambarOptionals untuk efisiensi
        $gambarUtama->load('gambarOptionals');

        // 2. Masukkan 3 path wajib
        $paths = [
            'utama' => $gambarUtama->path_gambar_utama,
            'terurai' => $gambarUtama->path_gambar_terurai,
            'kontruksi' => $gambarUtama->path_gambar_kontruksi,
        ];

        // 3. Cari apakah ada Gambar Optional dengan tipe 'paket'
        //    menggunakan collection filtering (tanpa query ulang ke DB)
        $paketOptional = $gambarUtama->gambarOptionals
            ->where('tipe', 'paket')
            ->first();

        // 4. Jika ketemu, tambahkan ke array response
        if ($paketOptional) {
            $paths['paket'] = $paketOptional->path_gambar_optional;
        }

        return response()->json($paths);
    }

    /**
     * Mengirimkan konten file PDF dari disk 'master_gambar'
     * berdasarkan path yang diberikan di query parameter.
     */
    public function viewPdf(Request $request)
    {
        $validated = $request->validate(['path' => 'required|string']);
        $path = $validated['path'];

        // Cek keamanan dasar agar tidak bisa mengakses file di luar direktori
        if (Str::contains($path, '..')) {
            abort(403, 'Akses tidak diizinkan.');
        }

        if (!Storage::disk('master_gambar')->exists($path)) {
            return response()->json(['message' => 'File PDF tidak ditemukan.'], 404);
        }

        $filePath = Storage::disk('master_gambar')->path($path);
        return response()->file($filePath, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Menghapus data Gambar Utama beserta file fisiknya.
     * Dan juga menghapus Gambar Optional (tipe paket) yang terkait.
     */
    public function destroy($id)
    {
        $gambarUtama = GGambarUtama::findOrFail($id);

        // 1. Hapus 3 File Utama dari Storage
        $filesToDelete = [
            $gambarUtama->path_gambar_utama,
            $gambarUtama->path_gambar_terurai,
            $gambarUtama->path_gambar_kontruksi,
        ];
        Storage::disk('master_gambar')->delete($filesToDelete);

        // 2. Cek & Hapus Gambar Optional Paket yang menempel (Jika ada)
        $paketOptionals = \App\Models\HGambarOptional::where('g_gambar_utama_id', $id)
            ->where('tipe', 'paket')
            ->get();

        foreach ($paketOptionals as $opt) {
            // Hapus file fisik optional
            Storage::disk('master_gambar')->delete($opt->path_gambar_optional);
            // Hapus record db optional
            $opt->forceDelete(); // Gunakan forceDelete agar bersih total
        }

        // 3. Hapus Record Gambar Utama
        $gambarUtama->delete(); // Atau forceDelete() jika tidak pakai SoftDeletes di model GGambarUtama

        return response()->noContent();
    }
}
