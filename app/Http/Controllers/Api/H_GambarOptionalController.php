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
        // 1. Validasi parameter
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:50,100',
            'sortBy' => 'nullable|string|in:id,type_engine,merk,type_chassis,jenis_kendaraan,tipe,varian_body,deskripsi,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 50;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query utama
        $query = HGambarOptional::query()
            ->join('e_varian_body', 'h_gambar_optional.e_varian_body_id', '=', 'e_varian_body.id')
            ->join('master_data', 'e_varian_body.master_data_id', '=', 'master_data.id')
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->select('h_gambar_optional.*');

        // 3. Eager load
        $query->with('varianBody.masterData.typeEngine', 'varianBody.masterData.merk', 'varianBody.masterData.typeChassis', 'varianBody.masterData.jenisKendaraan');

        // 4. Terapkan filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('h_gambar_optional.deskripsi', 'like', "%{$search}%")
                    // TAMBAHKAN PENCARIAN ID DI SINI
                    ->orWhere('h_gambar_optional.id', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.tipe', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.varian_body', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.created_at', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.updated_at', 'like', "%{$search}%");
            });
        }

        // 5. Terapkan sorting
        $sortColumn = match ($sortBy) {
            // TAMBAHKAN MAPPING ID DI SINI
            'id' => 'h_gambar_optional.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'varian_body' => 'e_varian_body.varian_body',
            'tipe' => 'h_gambar_optional.tipe',
            'deskripsi' => 'h_gambar_optional.deskripsi',
            'created_at' => 'h_gambar_optional.created_at',
            'updated_at' => 'h_gambar_optional.updated_at',
            default => 'h_gambar_optional.updated_at',
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 6. Lakukan paginasi
        return $query->paginate($perPage);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipe' => 'required|in:independen,paket',
            'deskripsi' => 'required|string|max:255',
            'gambar_optional' => 'required|file|mimes:pdf',
            'e_varian_body_id' => 'required_if:tipe,independen|exists:e_varian_body,id',
            'g_gambar_utama_id' => 'required_if:tipe,paket|exists:g_gambar_utama,id',
        ]);

        $tipe = $validated['tipe'];

        // Gunakan Transaction agar aman (jika upload gagal, data tidak tersimpan)
        return DB::transaction(function () use ($request, $validated, $tipe) {

            // --- 1. TENTUKAN BASE PATH & DATA PARENT ---
            $varianBody = null;
            $basePath = '';

            if ($tipe === 'independen') {
                $varianBody = EVarianBody::with('masterData')->find($validated['e_varian_body_id']);
                $basePath = $varianBody->master_data_id . '/' . $varianBody->id . '/independen';
            } else {
                // tipe === 'paket'
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
                'path_gambar_optional' => 'TEMP_PATH', // Placeholder
            ];

            if ($tipe === 'independen') {
                $createData['e_varian_body_id'] = $validated['e_varian_body_id'];
            } else {
                $createData['g_gambar_utama_id'] = $validated['g_gambar_utama_id'];
                $createData['e_varian_body_id'] = $varianBody->id;
            }

            // B. Simpan ke DB untuk dapat ID
            $gambarOptional = HGambarOptional::create($createData);

            // C. Sekarang ID sudah ada ($gambarOptional->id)
            $fileName = $gambarOptional->id . '.pdf';

            // D. Upload File Fisik
            $finalPath = $request->file('gambar_optional')->storeAs($basePath, $fileName, 'master_gambar');

            // E. Update record DB dengan path yang valid
            $gambarOptional->update([
                'path_gambar_optional' => $finalPath
            ]);

            return response()->json($gambarOptional->load('varianBody.masterData'), 201);
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
            'deskripsi' => 'required|string|max:255',
            'gambar_optional' => 'nullable|file|mimes:pdf', // File bersifat opsional saat edit
        ]);

        return DB::transaction(function () use ($request, $validated, $gambarOptional) {
            $updateData = [
                'deskripsi' => Str::upper($validated['deskripsi']),
            ];

            // Jika ada file baru yang diupload
            if ($request->hasFile('gambar_optional')) {
                // Tentukan Path (gunakan path lama atau generate ulang jika perlu)
                // Kita gunakan logika path yang sama dengan store: [master_id]/[varian_id]/[tipe]/[id].pdf

                $varianBody = $gambarOptional->varianBody;
                $masterDataId = $varianBody->master_data_id;
                $tipePath = ($gambarOptional->tipe === 'paket') ? 'paket' : 'independen';

                $basePath = "{$masterDataId}/{$varianBody->id}/{$tipePath}";
                $fileName = "{$gambarOptional->id}.pdf"; // Nama file tetap pakai ID

                // Hapus file lama jika ada (opsional, overwrite otomatis biasanya works)
                // Tapi untuk memastikan cache clear atau jika path berubah, delete dulu lebih aman.
                if (Storage::disk('master_gambar')->exists($gambarOptional->path_gambar_optional)) {
                    Storage::disk('master_gambar')->delete($gambarOptional->path_gambar_optional);
                }

                // Upload file baru
                $finalPath = $request->file('gambar_optional')->storeAs($basePath, $fileName, 'master_gambar');
                $updateData['path_gambar_optional'] = $finalPath;
            }

            $gambarOptional->update($updateData);

            return response()->json($gambarOptional->load('varianBody.masterData'));
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
