<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use App\Models\GGambarUtama;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\TransaksiDetail;
use Illuminate\Support\Facades\Log;

class GambarMasterController extends Controller
{
    public function uploadGambarUtama(Request $request)
    {

        // --- LOGGING INFO FILE DARI REQUEST ---
        Log::info('=== Menerima Request Upload Gambar Master ===');

        $filesToCheck = ['gambar_utama', 'gambar_terurai', 'gambar_kontruksi'];
        foreach ($filesToCheck as $fileKey) {
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                Log::info("File [{$fileKey}]:", [
                    'Original Name' => $file->getClientOriginalName(),
                    'MIME Type' => $file->getClientMimeType(),
                    'Size (Bytes)' => $file->getSize(),
                    'Extension' => $file->getClientOriginalExtension(),
                    'Error Code' => $file->getError()
                ]);
            } else {
                Log::info("File [{$fileKey}] tidak ditemukan dalam request.");
            }
        }
        Log::info('=============================================');
        // --- END LOGGING ---

        // 1. Validasi
        $validated = $request->validate([
            'master_data_id' => 'required|integer|exists:master_data,id',
            'varian_body' => 'required|string|max:255',
            'gambar_utama' => 'required|file|mimes:pdf|max:1024',
            'gambar_terurai' => 'nullable|file|mimes:pdf|max:1024',
            'gambar_kontruksi' => 'nullable|file|mimes:pdf|max:1024',
        ], [
            'gambar_utama.max' => 'Ukuran file Gambar Utama tidak boleh lebih dari 1 MB.',
            'gambar_terurai.max' => 'Ukuran file Gambar Terurai tidak boleh lebih dari 1 MB.',
            'gambar_kontruksi.max' => 'Ukuran file Gambar Kontruksi tidak boleh lebih dari 1 MB.',
        ]);

        // 2. Buat atau ambil Varian Body (case-insensitive & simpan as-is)
        $cleanVarian = trim($validated['varian_body']);
        $varianBody = EVarianBody::where('master_data_id', $validated['master_data_id'])
            ->whereRaw('LOWER(varian_body) = ?', [strtolower($cleanVarian)])
            ->first();
        if (!$varianBody) {
            $varianBody = EVarianBody::create([
                'master_data_id' => $validated['master_data_id'],
                'varian_body' => $cleanVarian,
            ]);
        }

        // 3. Bangun path dasar
        $basePath = $this->buildPath($varianBody);
        $existingGambar = GGambarUtama::where('e_varian_body_id', $varianBody->id)->first();
        $dataToUpdate = [];

        // --- PROSES GAMBAR UTAMA ---
        if ($existingGambar && $existingGambar->path_gambar_utama) {
            // CEK: Apakah file lama dipakai di transaksi?
            if (!$this->isPathUsedInTransaction($existingGambar->path_gambar_utama)) {
                // Jika TIDAK DIPAKAI (misal salah upload), HAPUS file fisiknya!
                Storage::disk('master_gambar')->delete($existingGambar->path_gambar_utama);
            }
        }
        $dataToUpdate['path_gambar_utama'] = $request->file('gambar_utama')->storeAs(
            $basePath,
            $this->buildFileName($varianBody->id, 'Gambar Utama'),
            'master_gambar'
        );

        // --- PROSES GAMBAR TERURAI ---
        if ($request->hasFile('gambar_terurai')) {
            if ($existingGambar && $existingGambar->path_gambar_terurai) {
                if (!$this->isPathUsedInTransaction($existingGambar->path_gambar_terurai)) {
                    Storage::disk('master_gambar')->delete($existingGambar->path_gambar_terurai);
                }
            }
            $dataToUpdate['path_gambar_terurai'] = $request->file('gambar_terurai')->storeAs(
                $basePath,
                $this->buildFileName($varianBody->id, 'Gambar Terurai'),
                'master_gambar'
            );
        }

        // --- PROSES GAMBAR KONTRUKSI ---
        if ($request->hasFile('gambar_kontruksi')) {
            if ($existingGambar && $existingGambar->path_gambar_kontruksi) {
                if (!$this->isPathUsedInTransaction($existingGambar->path_gambar_kontruksi)) {
                    Storage::disk('master_gambar')->delete($existingGambar->path_gambar_kontruksi);
                }
            }
            $dataToUpdate['path_gambar_kontruksi'] = $request->file('gambar_kontruksi')->storeAs(
                $basePath,
                $this->buildFileName($varianBody->id, 'Gambar Kontruksi'),
                'master_gambar'
            );
        }

        // 6. Simpan ke Database
        $gambarUtama = GGambarUtama::updateOrCreate(
            ['e_varian_body_id' => $varianBody->id],
            $dataToUpdate
        );

        if (!$gambarUtama->wasChanged()) {
            $gambarUtama->touch();
        }

        $gambarUtama->load('varianBody.masterData');
        return response()->json($gambarUtama, 201);
    }

    /**
     * --- FUNGSI PINTAR (SMART DELETE CHECK) ---
     * Cek apakah path file ini tercatat di JSON transaksi.
     */
    private function isPathUsedInTransaction(string $path): bool
    {
        if (empty($path)) return false;

        // Kita gunakan LIKE untuk mencari string path di dalam kolom JSON snapshot_data.
        // Ini sangat cepat dan efisien.
        return TransaksiDetail::where('snapshot_data', 'LIKE', '%"' . $path . '"%')->exists();
    }

    private function buildPath(EVarianBody $varianBody): string
    {
        return $varianBody->master_data_id . '/' . $varianBody->id;
    }

    private function buildFileName(int $varianId, string $suffix): string
    {
        // Tetap pakai time() agar unik
        return $varianId . '_' . time() . '_' . Str::slug($suffix, '-') . '.pdf';
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

        // 2. Siapkan array paths
        $paths = [
            'utama' => $gambarUtama->path_gambar_utama, // Gambar utama pasti ada
        ];

        // 3. Masukkan Terurai & Kontruksi HANYA JIKA ADA (Tidak Null)
        if ($gambarUtama->path_gambar_terurai) {
            $paths['terurai'] = $gambarUtama->path_gambar_terurai;
        }

        if ($gambarUtama->path_gambar_kontruksi) {
            $paths['kontruksi'] = $gambarUtama->path_gambar_kontruksi;
        }

        // 4. Cari apakah ada Gambar Optional dengan tipe 'paket'
        $paketOptional = $gambarUtama->gambarOptionals
            ->where('tipe', 'paket')
            ->first();

        // 5. Jika ketemu, tambahkan ke array response
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

        // 1. Kumpulkan file yang ADA saja
        $filesToDelete = [
            $gambarUtama->path_gambar_utama, // Wajib ada
        ];

        if ($gambarUtama->path_gambar_terurai) {
            $filesToDelete[] = $gambarUtama->path_gambar_terurai;
        }

        if ($gambarUtama->path_gambar_kontruksi) {
            $filesToDelete[] = $gambarUtama->path_gambar_kontruksi;
        }

        // Hapus fisik
        Storage::disk('master_gambar')->delete($filesToDelete);

        // 2. Cek & Hapus Gambar Optional Paket (TETAP SAMA)
        $paketOptionals = \App\Models\HGambarOptional::where('g_gambar_utama_id', $id)
            ->where('tipe', 'paket')
            ->get();

        foreach ($paketOptionals as $opt) {
            if ($opt->path_gambar_optional) {
                Storage::disk('master_gambar')->delete($opt->path_gambar_optional);
            }
            $opt->forceDelete();
        }

        // 3. Hapus Record Gambar Utama
        $gambarUtama->delete();

        return response()->noContent();
    }
}
