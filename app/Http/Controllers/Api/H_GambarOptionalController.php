<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use App\Models\HGambarOptional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class H_GambarOptionalController extends Controller
{
    /**
     * Menampilkan data gambar optional dengan paginasi, filter, dan sort
     * (Sekarang menggunakan relasi MasterData)
     */
    public function index(Request $request)
    {
        // 1. Validasi
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:50,100',
            'sortBy' => 'nullable|string',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 50;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query Utama (KHUSUS INDEPENDEN)
        $query = HGambarOptional::query()
            ->where('h_gambar_optional.tipe', 'independen') // Filter Wajib
            ->join('master_data', 'h_gambar_optional.master_data_id', '=', 'master_data.id')
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->select('h_gambar_optional.*');

        // 3. Eager Load (Load relasi MasterData langsung)
        $query->with(['masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan']);

        // 4. Filter Pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('h_gambar_optional.deskripsi', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.id', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.created_at', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.updated_at', 'like', "%{$search}%");
            });
        }

        // 5. Sorting
        $sortColumn = match ($sortBy) {
            'id' => 'h_gambar_optional.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'deskripsi' => 'h_gambar_optional.deskripsi',
            'created_at' => 'h_gambar_optional.created_at',
            'updated_at' => 'h_gambar_optional.updated_at',
            default => 'h_gambar_optional.updated_at',
        };
        $query->orderBy($sortColumn, $sortDirection);

        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipe' => 'required|in:independen,paket',
            'deskripsi' => 'required|string|max:255',
            'gambar_optional' => 'required|file|mimes:pdf|max:1024',
            'master_data_id' => 'required_if:tipe,independen|exists:master_data,id',
            'g_gambar_utama_id' => 'required_if:tipe,paket|exists:g_gambar_utama,id',
        ], [
            'gambar_optional.max' => 'Ukuran file PDF tidak boleh lebih dari 1 MB.',
        ]);

        $tipe = $validated['tipe'];

        return DB::transaction(function () use ($request, $validated, $tipe) {
            $basePath = '';

            // --- PERUBAHAN LOGIKA PATH & ID ---
            if ($tipe === 'independen') {
                $masterDataId = $validated['master_data_id'];
                // Path baru: master_data/{id}/independen
                $basePath = $masterDataId . '/independen';
            } else {
                // Tipe Paket (Tetap sama)
                $gambarUtama = \App\Models\GGambarUtama::with('varianBody.masterData')->find($validated['g_gambar_utama_id']);
                $varianBody = $gambarUtama->varianBody;
                $basePath = $varianBody->master_data_id . '/' . $varianBody->id . '/paket';
            }

            // --- 2. LOGIKA KHUSUS TIPE 'PAKET' (UPSERT / Update jika ada) ---
            if ($tipe === 'paket') {
                $existingOptional = HGambarOptional::where('g_gambar_utama_id', $validated['g_gambar_utama_id'])
                    ->where('tipe', 'paket')
                    ->first();

                if ($existingOptional) {
                    // Gunakan ID yang SUDAH ADA sebagai nama file
                    $fileName = $existingOptional->id . '.pdf';

                    // Upload File Baru (akan menimpa file lama jika namanya sama)
                    $newPath = $request->file('gambar_optional')->storeAs($basePath, $fileName, 'master_gambar');

                    // Hapus file lama jika ternyata path/namanya beda (misal dulu pakai slug)
                    if ($existingOptional->path_gambar_optional !== $newPath) {
                        if (Storage::disk('master_gambar')->exists($existingOptional->path_gambar_optional)) {
                            Storage::disk('master_gambar')->delete($existingOptional->path_gambar_optional);
                        }
                    }

                    // Update Data
                    $existingOptional->update([
                        'deskripsi' => Str::upper($validated['deskripsi']),
                        'path_gambar_optional' => $newPath,
                    ]);

                    return response()->json($existingOptional->load('varianBody.masterData'), 200);
                }
            }

            // --- 3. LOGIKA CREATE BARU (Independen ATAU Paket baru) ---

            // A. Siapkan data create (Path dikosongkan dulu atau kasih string sementara)
            $createData = [
                'tipe' => $tipe,
                'deskripsi' => Str::upper($validated['deskripsi']),
                'path_gambar_optional' => 'TEMP_PATH',
            ];

            if ($tipe === 'independen') {
                // SIMPAN KE MASTER DATA ID
                $createData['master_data_id'] = $validated['master_data_id'];
            } else {
                $createData['g_gambar_utama_id'] = $validated['g_gambar_utama_id'];
                // Optional: e_varian_body_id bisa dihapus atau tetap diisi null
            }

            $gambarOptional = HGambarOptional::create($createData);

            // C. Sekarang ID sudah ada ($gambarOptional->id)
            $fileName = $gambarOptional->id . '.pdf';

            // D. Upload File Fisik
            $finalPath = $request->file('gambar_optional')->storeAs($basePath, $fileName, 'master_gambar');

            // E. Update record DB dengan path yang valid
            $gambarOptional->update([
                'path_gambar_optional' => $finalPath
            ]);

            return response()->json($gambarOptional, 201);
        });
    }

    /**
     * Memperbarui deskripsi gambar optional.
     */
    public function update(Request $request, HGambarOptional $gambarOptional)
    {
        $validated = $request->validate([
            'deskripsi' => 'required|string|max:255',
        ]);

        $gambarOptional->update([
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        // Ambil kembali data dengan relasi yang benar
        $updatedItem = $gambarOptional->fresh()->load('varianBody.masterData.typeEngine', 'varianBody.masterData.merk', 'varianBody.masterData.typeChassis', 'varianBody.masterData.jenisKendaraan');

        return response()->json($updatedItem);
    }
    /**
     * Update Gambar Optional (Deskripsi DAN File).
     * Mode Edit dari Frontend akan memanggil ini.
     */
    public function updateFile(Request $request, HGambarOptional $gambarOptional)
    {
        $validated = $request->validate([
            'deskripsi' => 'nullable|string|max:255',
            'gambar_optional' => 'nullable|file|mimes:pdf|max:1024',
        ], [
            'gambar_optional.max' => 'Ukuran file PDF tidak boleh lebih dari 1 MB.',
        ]);

        return DB::transaction(function () use ($request, $validated, $gambarOptional) {
            $updateData = [];

            // A. Cek Update Deskripsi
            if ($request->filled('deskripsi')) {
                $updateData['deskripsi'] = Str::upper($validated['deskripsi']);
            }

            // B. Cek Update File
            if ($request->hasFile('gambar_optional')) {
                $basePath = '';
                $fileName = "{$gambarOptional->id}.pdf";

                // --- PERBAIKAN LOGIKA PATH ---
                if ($gambarOptional->tipe === 'independen') {
                    // 1. Tipe Independen: Ambil langsung master_data_id
                    // Path: {master_id}/independen/{id}.pdf
                    $basePath = "{$gambarOptional->master_data_id}/independen";
                } else {
                    // 2. Tipe Paket: Ambil via Relasi Gambar Utama -> Varian Body
                    // Path: {master_id}/{varian_id}/paket/{id}.pdf

                    // Load relasi gambar utama -> varian body
                    $gambarOptional->load('gambarUtama.varianBody');

                    $gambarUtama = $gambarOptional->gambarUtama;
                    // Safety check jika data corrupt
                    if (!$gambarUtama || !$gambarUtama->varianBody) {
                        throw new \Exception("Data Varian Body tidak ditemukan untuk gambar paket ini.");
                    }

                    $varianBody = $gambarUtama->varianBody;
                    $basePath = "{$varianBody->master_data_id}/{$varianBody->id}/paket";
                }
                // -----------------------------

                // Hapus file lama jika ada
                if (Storage::disk('master_gambar')->exists($gambarOptional->path_gambar_optional)) {
                    Storage::disk('master_gambar')->delete($gambarOptional->path_gambar_optional);
                }

                // Upload file baru
                $finalPath = $request->file('gambar_optional')->storeAs($basePath, $fileName, 'master_gambar');
                $updateData['path_gambar_optional'] = $finalPath;
            }

            if (!empty($updateData)) {
                $gambarOptional->update($updateData);
            }
            $gambarOptional->touch();

            // Load relasi untuk respon JSON (Conditional loading agar tidak error)
            if ($gambarOptional->tipe === 'independen') {
                return response()->json($gambarOptional->load('masterData'));
            } else {
                return response()->json($gambarOptional->load('gambarUtama.varianBody.masterData'));
            }
        });
    }
    /**
     * Menghapus (Soft Delete) gambar optional.
     */
    public function destroy(HGambarOptional $gambarOptional)
    {
        if ($gambarOptional->path_gambar_optional && Storage::disk('master_gambar')->exists($gambarOptional->path_gambar_optional)) {
            Storage::disk('master_gambar')->delete($gambarOptional->path_gambar_optional);
        }

        $gambarOptional->delete(); // Lakukan Soft Delete

        return response()->noContent();
    }

    /**
     * Menampilkan file PDF.
     */
    public function showPdf(HGambarOptional $gambarOptional)
    {
        $path = $gambarOptional->path_gambar_optional;

        if (!Storage::disk('master_gambar')->exists($path)) {
            return response()->json(['message' => 'File PDF tidak ditemukan.'], 404);
        }

        $filePath = Storage::disk('master_gambar')->path($path);
        return response()->file($filePath, ['Content-Type' => 'application/pdf']);
    }
}
