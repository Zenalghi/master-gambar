<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CTypeChassis;
use App\Models\IGambarKelistrikan;
use App\Models\MasterKelistrikanFile;
use App\Models\MasterData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class I_GambarKelistrikanController extends Controller
{
    // === BAGIAN 1: GUDANG FILE (MasterKelistrikanFile) ===

    public function indexFiles(Request $request)
    {
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:50,100,200',
            'sortBy' => 'nullable|string',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 50;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // Query ke tabel FILE FISIK
        $query = \App\Models\MasterKelistrikanFile::query()
            ->join('c_type_chassis', 'master_kelistrikan_files.c_type_chassis_id', '=', 'c_type_chassis.id')

            // --- PERBAIKAN UTAMA DI SINI ---
            // Karena Chassis independen, kita cari info Merk/Engine lewat Master Data
            // Kita gunakan leftJoin agar file tetap muncul meski Chassis belum dipakai di Master Data
            ->leftJoin('master_data', 'c_type_chassis.id', '=', 'master_data.c_type_chassis_id')
            ->leftJoin('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->leftJoin('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            // -------------------------------

            ->select([
                'master_kelistrikan_files.*',
                'c_type_chassis.type_chassis as chassis_name',
                // Ambil salah satu merk/engine (karena left join bisa duplikat, kita group nanti)
                'b_merks.merk as merk_name',
                'a_type_engines.type_engine as engine_name',
            ])
            // PENTING: Group By ID File agar tidak muncul baris ganda 
            // jika satu chassis dipakai oleh banyak Master Data
            ->groupBy('master_kelistrikan_files.id');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('master_kelistrikan_files.id', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortColumn = match ($sortBy) {
            'id' => 'master_kelistrikan_files.id',
            'type_engine' => 'engine_name', // Sesuai alias di select
            'merk' => 'merk_name',          // Sesuai alias di select
            'type_chassis' => 'chassis_name', // Sesuai alias di select
            'created_at' => 'master_kelistrikan_files.created_at',
            'updated_at' => 'master_kelistrikan_files.updated_at',
            default => 'master_kelistrikan_files.updated_at',
        };
        $query->orderBy($sortColumn, $sortDirection);

        return $query->paginate($perPage);
    }

    public function storeFile(Request $request)
    {
        $validated = $request->validate([
            'c_type_chassis_id' => 'required|integer|exists:c_type_chassis,id',
            'gambar_kelistrikan' => 'required|file|mimes:pdf',
        ]);

        $chassisId = $validated['c_type_chassis_id'];

        // Cek apakah sudah ada file untuk chassis ini?
        $existingFile = MasterKelistrikanFile::where('c_type_chassis_id', $chassisId)->first();
        if ($existingFile) {
            // Hapus file lama jika ingin replace, atau tolak.
            // Di sini kita replace:
            if (Storage::disk('master_gambar')->exists($existingFile->path_file)) {
                Storage::disk('master_gambar')->delete($existingFile->path_file);
            }
            $fileRecord = $existingFile;
        } else {
            $fileRecord = new MasterKelistrikanFile();
            $fileRecord->c_type_chassis_id = $chassisId;
        }

        // Simpan File
        // Path: kelistrikan/{chassis_id}/{timestamp}.pdf
        // Menggunakan timestamp agar unik dan menghindari masalah cache
        $fileName = time() . '.pdf';
        $path = 'kelistrikan/' . $chassisId . '/' . $fileName; // Path Relatif

        // Simpan fisik
        $request->file('gambar_kelistrikan')->storeAs('kelistrikan/' . $chassisId, $fileName, 'master_gambar');

        $fileRecord->path_file = $path;
        $fileRecord->save();

        return response()->json($fileRecord, 201);
    }

    public function destroyFile($id)
    {
        $file = MasterKelistrikanFile::findOrFail($id);
        if (Storage::disk('master_gambar')->exists($file->path_file)) {
            Storage::disk('master_gambar')->delete($file->path_file);
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
        ]);

        $masterData = MasterData::findOrFail($validated['master_data_id']);

        // Cari file fisik berdasarkan chassis dari Master Data
        $fileFisik = MasterKelistrikanFile::where('c_type_chassis_id', $masterData->c_type_chassis_id)->first();

        if (!$fileFisik) {
            return response()->json(['message' => 'File PDF belum tersedia untuk Chassis ini. Silakan upload di menu Gambar Kelistrikan.'], 422);
        }

        // Update or Create Deskripsi
        $gambar = IGambarKelistrikan::updateOrCreate(
            ['master_data_id' => $masterData->id],
            [
                'master_kelistrikan_file_id' => $fileFisik->id,
                'deskripsi' => Str::upper($validated['deskripsi']),
            ]
        );

        return response()->json($gambar, 200);
    }

    public function checkFileStatus($chassisId)
    {
        $existingFile = MasterKelistrikanFile::where('c_type_chassis_id', $chassisId)->first();
        return response()->json([
            'exists' => $existingFile !== null,
            'filename' => $existingFile ? basename($existingFile->path_file) : null
        ]);
    }
}
