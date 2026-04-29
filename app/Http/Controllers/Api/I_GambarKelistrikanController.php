<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB; // Penting untuk Transaction
use Illuminate\Support\Str;
use App\Models\MasterKelistrikanFile;
use App\Models\MasterData;
use App\Models\IGambarKelistrikan;
use App\Models\TransaksiDetail; // <-- Import ini untuk pengecekan Snapshot

class I_GambarKelistrikanController extends Controller
{
    // === 1. LIST FILE (GUDANG FILE) ===
    public function indexFiles(Request $request)
    {
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

        // Query Langsung ke 3 Tabel Induk
        $query = \App\Models\MasterKelistrikanFile::query()
            ->join('a_type_engines', 'master_kelistrikan_files.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_kelistrikan_files.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_kelistrikan_files.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->select([
                'master_kelistrikan_files.*',
                'a_type_engines.type_engine',
                'b_merks.merk',
                'c_type_chassis.type_chassis'
            ]);

        // Search Logic
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('master_kelistrikan_files.id', 'like', "%{$search}%")
                    ->orWhere('master_kelistrikan_files.created_at', 'like', "%{$search}%")
                    ->orWhere('master_kelistrikan_files.updated_at', 'like', "%{$search}%");
            });
        }

        // Sorting Logic
        $sortColumn = match ($sortBy) {
            'id' => 'master_kelistrikan_files.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'created_at' => 'master_kelistrikan_files.created_at',
            'updated_at' => 'master_kelistrikan_files.updated_at',
            default => 'master_kelistrikan_files.updated_at',
        };
        $query->orderBy($sortColumn, $sortDirection);

        return $query->paginate($perPage);
    }

    // === 2. UPLOAD FILE (TRANSACTION LOGIC) ===
    public function storeFile(Request $request)
    {
        $validated = $request->validate([
            'a_type_engine_id' => 'required|integer|exists:a_type_engines,id',
            'b_merk_id' => 'required|integer|exists:b_merks,id',
            'c_type_chassis_id' => 'required|integer|exists:c_type_chassis,id',
            'gambar_kelistrikan' => 'required|file|mimes:pdf|max:1024',
        ], [
            'gambar_kelistrikan.max' => 'Ukuran file PDF tidak boleh lebih dari 1 MB.',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // 1. Cek Data Lama
            $fileRecord = MasterKelistrikanFile::where('a_type_engine_id', $validated['a_type_engine_id'])
                ->where('b_merk_id', $validated['b_merk_id'])
                ->where('c_type_chassis_id', $validated['c_type_chassis_id'])
                ->first();

            if (!$fileRecord) {
                // Buat record baru sementara (tanpa path) untuk dapat ID
                $fileRecord = new MasterKelistrikanFile();
                $fileRecord->a_type_engine_id = $validated['a_type_engine_id'];
                $fileRecord->b_merk_id = $validated['b_merk_id'];
                $fileRecord->c_type_chassis_id = $validated['c_type_chassis_id'];
                $fileRecord->path_file = 'temp';
                $fileRecord->save();
            }

            // --- AMBIL NAMA TYPE ENGINE ---
            $engine = \App\Models\ATypeEngine::find($validated['a_type_engine_id']);
            $engineName = $engine ? Str::slug($engine->type_engine, '-') : 'engine';

            // 2. GENERATE PATH BARU DENGAN TYPE ENGINE & TIMESTAMP
            $chassisId = $validated['c_type_chassis_id'];
            $fileId = $fileRecord->id;

            // Format: kelistrikan/{chassis_id}/{type_engine}_{file_id}_{timestamp}.pdf
            // Contoh: kelistrikan/5/euro-4_12_1715423811.pdf
            $fileName = $engineName . '_' . $fileId . '_' . time() . '.pdf';
            $directory = 'kelistrikan/' . $chassisId;

            $newPath = $request->file('gambar_kelistrikan')->storeAs(
                $directory,
                $fileName,
                'master_gambar'
            );

            // 3. SMART DELETE (Hapus File Lama jika tidak terpakai)
            if ($fileRecord->path_file && $fileRecord->path_file !== 'temp' && $fileRecord->path_file !== $newPath) {
                if (!$this->isPathUsedInTransaction($fileRecord->path_file)) {
                    if (Storage::disk('master_gambar')->exists($fileRecord->path_file)) {
                        Storage::disk('master_gambar')->delete($fileRecord->path_file);
                    }
                }
            }

            // 4. Update Path di Database
            $fileRecord->path_file = $newPath;
            $fileRecord->save();
            $fileRecord->touch();

            return response()->json($fileRecord, 201);
        });
    }

    // === 3. DELETE FILE ===
    public function destroyFile($id)
    {
        $file = MasterKelistrikanFile::findOrFail($id);

        // --- SMART DELETE ---
        if ($file->path_file && Storage::disk('master_gambar')->exists($file->path_file)) {
            // Cek apakah file dipakai di transaksi
            if (!$this->isPathUsedInTransaction($file->path_file)) {
                Storage::disk('master_gambar')->delete($file->path_file);
            }
        }

        $file->delete();
        return response()->noContent();
    }


    // === BAGIAN 2: LOGIKA DESKRIPSI (MASTER DATA) ===

    public function storeDeskripsi(Request $request)
    {
        $validated = $request->validate([
            'master_data_id' => 'required|integer|exists:master_data,id',
            'deskripsi' => 'required|string|max:255',
            'id' => 'nullable|integer|exists:i_gambar_kelistrikan,id'
        ]);

        $masterData = \App\Models\MasterData::findOrFail($validated['master_data_id']);

        // Cari file fisik
        $fileFisik = \App\Models\MasterKelistrikanFile::where('c_type_chassis_id', $masterData->c_type_chassis_id)->first();

        if (!$fileFisik) {
            return response()->json(['message' => 'File PDF belum tersedia untuk Chassis ini.'], 422);
        }

        if ($request->filled('id')) {
            // Mode Edit Existing Option
            $gambar = \App\Models\IGambarKelistrikan::findOrFail($request->id);
            $gambar->update([
                'deskripsi' => \Illuminate\Support\Str::upper($validated['deskripsi'])
            ]);
        } else {
            // Mode Add New Option
            $gambar = \App\Models\IGambarKelistrikan::firstOrCreate(
                [
                    'master_data_id' => $masterData->id,
                    'deskripsi' => \Illuminate\Support\Str::upper($validated['deskripsi'])
                ],
                [
                    'master_kelistrikan_file_id' => $fileFisik->id,
                ]
            );
        }

        return response()->json($gambar, 200);
    }

    public function destroyDeskripsi($id)
    {
        $gambar = \App\Models\IGambarKelistrikan::findOrFail($id);

        // CEK JUMLAH DATA
        $count = \App\Models\IGambarKelistrikan::where('master_data_id', $gambar->master_data_id)->count();

        if ($count <= 1) {
            return response()->json([
                'message' => 'Tidak dapat menghapus. Minimal harus tersisa 1 deskripsi.'
            ], 422);
        }

        $gambar->delete();

        return response()->noContent(); // 204 Success
    }

    public function checkFileStatus($chassisId)
    {
        $existingFile = MasterKelistrikanFile::where('c_type_chassis_id', $chassisId)->first();
        return response()->json([
            'exists' => $existingFile !== null,
            'filename' => $existingFile ? basename($existingFile->path_file) : null
        ]);
    }

    /**
     * Menampilkan file PDF Kelistrikan.
     * (Saya tambahkan helper method ini agar konsisten dengan controller lain jika dibutuhkan view)
     */
    public function showPdf(IGambarKelistrikan $gambarKelistrikan)
    {
        $fileFisik = $gambarKelistrikan->fileKelistrikan;

        if (!$fileFisik || !Storage::disk('master_gambar')->exists($fileFisik->path_file)) {
            return response()->json(['message' => 'File PDF tidak ditemukan.'], 404);
        }

        $filePath = Storage::disk('master_gambar')->path($fileFisik->path_file);
        return response()->file($filePath, ['Content-Type' => 'application/pdf']);
    }

    /**
     * --- FUNGSI PINTAR (SMART DELETE CHECK) ---
     * Mengecek apakah path file PDF kelistrikan ini masih tercatat di snapshot transaksi historis.
     */
    private function isPathUsedInTransaction(string $path): bool
    {
        if (empty($path)) return false;

        // Pengecekan aman dan super cepat via LIKE query JSON
        return TransaksiDetail::where('snapshot_data', 'LIKE', '%"' . $path . '"%')->exists();
    }
}
