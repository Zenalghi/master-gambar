<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IGambarKelistrikan;
use App\Models\MasterKelistrikanFile;
use App\Models\MasterData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class I_GambarKelistrikanController extends Controller
{
    public function index(Request $request)
    {
        // Validasi Paging
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // Query utama
        $query = IGambarKelistrikan::query()
            ->join('master_data', 'i_gambar_kelistrikan.master_data_id', '=', 'master_data.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->select('i_gambar_kelistrikan.*');

        $query->with(['masterData.typeChassis', 'fileKelistrikan']);

        // Search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('i_gambar_kelistrikan.deskripsi', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%");
            });
        }

        // Sorting
        $query->orderBy('i_gambar_kelistrikan.updated_at', $sortDirection);

        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_data_id' => [
                'required',
                'integer',
                'exists:master_data,id',
                Rule::unique('i_gambar_kelistrikan')->whereNull('deleted_at')
            ],
            'deskripsi' => 'required|string|max:255',
            'gambar_kelistrikan' => 'nullable|file|mimes:pdf',
        ]);

        $masterData = MasterData::findOrFail($validated['master_data_id']);
        $chassisId = $masterData->c_type_chassis_id;

        // 1. Cek apakah chassis sudah punya file kelistrikan
        $existingFile = MasterKelistrikanFile::where('c_type_chassis_id', $chassisId)->first();
        $fileId = null;

        if ($existingFile) {
            // A. Sudah punya file → gunakan file existing
            $fileId = $existingFile->id;
        } else {
            // B. Belum punya file → wajib upload file PDF
            if (!$request->hasFile('gambar_kelistrikan')) {
                return response()->json(['message' => 'File PDF wajib diupload untuk Chassis baru ini.'], 422);
            }

            // Path folder berdasarkan Master Data (terstruktur rapi)
            $chassis = $masterData->typeChassis;
            $pathData = [
                $masterData->typeEngine->type_engine,
                $masterData->merk->merk,
                $chassis->type_chassis,
                'kelistrikan'
            ];

            $basePath = implode('/', array_map(
                fn($part) => Str::slug($part),
                $pathData
            ));

            // Nama file generik
            $fileName = Str::slug($chassis->type_chassis) . '_base.pdf';

            $path = $request->file('gambar_kelistrikan')->storeAs($basePath, $fileName, 'master_gambar');

            // Simpan file ke database
            $newFile = MasterKelistrikanFile::create([
                'c_type_chassis_id' => $chassisId,
                'path_file' => $path
            ]);

            $fileId = $newFile->id;
        }

        // 2. Simpan entri IGambarKelistrikan
        $gambar = IGambarKelistrikan::create([
            'master_data_id' => $validated['master_data_id'],
            'master_kelistrikan_file_id' => $fileId,
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        return response()->json($gambar, 201);
    }

    public function update(Request $request, IGambarKelistrikan $gambarKelistrikan)
    {
        $validated = $request->validate([
            'deskripsi' => 'required|string|max:255',
        ]);

        $gambarKelistrikan->update([
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        return response()->json($gambarKelistrikan->load('masterData', 'fileKelistrikan'));
    }

    public function destroy(IGambarKelistrikan $gambarKelistrikan)
    {
        $gambarKelistrikan->delete();
        return response()->noContent();
    }

    public function showPdf(IGambarKelistrikan $gambarKelistrikan)
    {
        $path = $gambarKelistrikan->fileKelistrikan->path_file ?? null;

        if (!$path || !Storage::disk('master_gambar')->exists($path)) {
            return response()->json(['message' => 'File PDF tidak ditemukan.'], 404);
        }

        $filePath = Storage::disk('master_gambar')->path($path);

        return response()->file($filePath, ['Content-Type' => 'application/pdf']);
    }

    // Helper untuk frontend
    public function checkFileStatus($masterDataId)
    {
        $masterData = MasterData::find($masterDataId);
        if (!$masterData) {
            return response()->json(['exists' => false]);
        }

        $file = MasterKelistrikanFile::where('c_type_chassis_id', $masterData->c_type_chassis_id)->first();

        return response()->json([
            'exists' => $file !== null,
            'filename' => $file ? basename($file->path_file) : null
        ]);
    }
}
