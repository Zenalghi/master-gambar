<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\GGambarUtama;
use App\Models\HGambarOptional;
use App\Models\MasterKelistrikanFile;
use App\Models\TransaksiDetail;
use Illuminate\Support\Str;

class GarbageCollectorController extends Controller
{
    /**
     * Endpoint 1: Bersihkan Sampah Gambar Utama (Utama, Terurai, Kontruksi)
     */
    public function cleanUtama()
    {
        return $this->processGarbage(['gambar-utama', 'gambar-terurai', 'gambar-kontruksi'], 'Gambar Utama');
    }

    /**
     * Endpoint 2: Bersihkan Sampah Gambar Optional (Paket & Independen)
     */
    public function cleanOptional()
    {
        return $this->processGarbage(['optional', 'paket', 'independen'], 'Gambar Optional');
    }

    /**
     * Endpoint 3: Bersihkan Sampah Gambar Kelistrikan
     */
    public function cleanKelistrikan()
    {
        return $this->processGarbage(['kelistrikan'], 'Gambar Kelistrikan');
    }

    /**
     * (Opsional) Endpoint 4: Sapu Bersih TOTAL (Nuke)
     * Menghapus semua file yatim piatu tanpa pandang bulu kategorinya.
     */
    public function cleanAll()
    {
        return $this->processGarbage([], 'Semua Kategori (Global)');
    }

    /**
     * -------------------------------------------------------------------
     * HELPER FUNCTIONS
     * -------------------------------------------------------------------
     */

    /**
     * Logic inti untuk mengeksekusi penghapusan berdasarkan kata kunci.
     */
    private function processGarbage(array $keywords, string $categoryName)
    {
        $allPhysicalFiles = Storage::disk('master_gambar')->allFiles();
        $validPaths = $this->getAllValidPaths();

        $deletedCount = 0;
        $deletedFiles = [];

        foreach ($allPhysicalFiles as $file) {
            // Cek 1: Apakah file ini yatim piatu? (Tidak ada di DB/Snapshot)
            if (!in_array($file, $validPaths)) {

                // Cek 2: Apakah kita memfilter berdasarkan keyword kategori?
                if (empty($keywords) || Str::contains(strtolower($file), $keywords)) {
                    Storage::disk('master_gambar')->delete($file);
                    $deletedFiles[] = $file;
                    $deletedCount++;
                }
            }
        }

        return response()->json([
            'message' => "Proses sapu bersih $categoryName selesai.",
            'total_deleted' => $deletedCount,
            'deleted_files' => $deletedFiles
        ]);
    }

    /**
     * Mengumpulkan SEMUA path yang sah (sedang dipakai di Master atau Snapshot Transaksi).
     * Ini menjamin tidak ada file valid yang terhapus tak sengaja.
     */
    private function getAllValidPaths(): array
    {
        $usedPaths = [];

        // 1. Ambil dari Master Gambar Utama
        foreach (GGambarUtama::all() as $g) {
            if ($g->path_gambar_utama) $usedPaths[] = $g->path_gambar_utama;
            if ($g->path_gambar_terurai) $usedPaths[] = $g->path_gambar_terurai;
            if ($g->path_gambar_kontruksi) $usedPaths[] = $g->path_gambar_kontruksi;
        }

        // 2. Ambil dari Master Gambar Optional
        foreach (HGambarOptional::all() as $opt) {
            if ($opt->path_gambar_optional) $usedPaths[] = $opt->path_gambar_optional;
        }

        // 3. Ambil dari Master Kelistrikan
        foreach (MasterKelistrikanFile::all() as $kel) {
            if ($kel->path_file) $usedPaths[] = $kel->path_file;
        }

        // 4. Ambil dari Snapshot Transaksi Detail (YANG PALING PENTING)
        $transaksis = TransaksiDetail::whereNotNull('snapshot_data')->get();
        foreach ($transaksis as $trx) {
            $jsonString = json_encode($trx->snapshot_data);

            // Regex mencari semua teks di dalam kutip ganda yang berakhiran .pdf
            preg_match_all('/"([^"]+\.pdf)"/', $jsonString, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $path) {
                    $usedPaths[] = $path; // Masukkan file snapshot ke daftar aman
                }
            }
        }

        return array_unique($usedPaths); // Bersihkan duplikat agar proses lebih ringan
    }
}
