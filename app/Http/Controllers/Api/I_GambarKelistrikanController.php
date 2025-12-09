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

        // Query simpel langsung ke 3 tabel induk
        $query = \App\Models\MasterKelistrikanFile::query()
            ->join('a_type_engines', 'master_kelistrikan_files.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_kelistrikan_files.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_kelistrikan_files.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->select([
                'master_kelistrikan_files.*',
                'a_type_engines.type_engine as engine_name',
                'b_merks.merk as merk_name',
                'c_type_chassis.type_chassis as chassis_name',
            ]);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('master_kelistrikan_files.id', 'like', "%{$search}%");
            });
        }

        // Sorting
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

    public function storeFile(Request $request)
    {
        $validated = $request->validate([
            'a_type_engine_id' => 'required|integer|exists:a_type_engines,id',
            'b_merk_id' => 'required|integer|exists:b_merks,id',
            'c_type_chassis_id' => 'required|integer|exists:c_type_chassis,id',
            'gambar_kelistrikan' => 'required|file|mimes:pdf',
        ]);

        // Cek duplikasi kombinasi
        $exists = \App\Models\MasterKelistrikanFile::where('a_type_engine_id', $validated['a_type_engine_id'])
            ->where('b_merk_id', $validated['b_merk_id'])
            ->where('c_type_chassis_id', $validated['c_type_chassis_id'])
            ->first();

        if ($exists) {
            // Hapus file lama jika ada (Replace)
            if (Storage::disk('master_gambar')->exists($exists->path_file)) {
                Storage::disk('master_gambar')->delete($exists->path_file);
            }
            $fileRecord = $exists;
        } else {
            $fileRecord = new \App\Models\MasterKelistrikanFile();
            $fileRecord->a_type_engine_id = $validated['a_type_engine_id'];
            $fileRecord->b_merk_id = $validated['b_merk_id'];
            $fileRecord->c_type_chassis_id = $validated['c_type_chassis_id'];
        }

        // Ambil nama untuk path (Manual lookup karena model belum tersimpan)
        $engine = \App\Models\ATypeEngine::find($validated['a_type_engine_id'])->type_engine;
        $merk = \App\Models\BMerk::find($validated['b_merk_id'])->merk;
        $chassis = \App\Models\CTypeChassis::find($validated['c_type_chassis_id'])->type_chassis;

        $pathData = [$engine, $merk, $chassis, 'kelistrikan'];
        $basePath = implode('/', array_map(fn($p) => Str::slug($p), $pathData));
        $fileName = Str::slug($chassis) . '_base.pdf';

        $path = $request->file('gambar_kelistrikan')->storeAs($basePath, $fileName, 'master_gambar');

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
