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
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('i_gambar_kelistrikan.created_at', 'like', "%{$search}%")
                    ->orWhere('i_gambar_kelistrikan.updated_at', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortColumn = match ($sortBy) {
            'id' => 'i_gambar_kelistrikan.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'deskripsi' => 'i_gambar_kelistrikan.deskripsi',
            'created_at' => 'i_gambar_kelistrikan.created_at',
            'updated_at' => 'i_gambar_kelistrikan.updated_at',

            default => 'i_gambar_kelistrikan.updated_at',
        };

        $query->orderBy($sortColumn, $sortDirection);

        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        // 1. Validasi Awal
        $validated = $request->validate([
            'master_data_id' => [
                'required',
                'integer',
                'exists:master_data,id',
                \Illuminate\Validation\Rule::unique('i_gambar_kelistrikan')
            ],
            'deskripsi' => 'required|string|max:255',
            'gambar_kelistrikan' => 'nullable|file|mimes:pdf',
        ]);

        // 2. Ambil MasterData dan ID Chassis yang Valid
        // Gunakan findOrFail agar langsung error 404 jika ID salah, mencegah null pointer
        $masterData = \App\Models\MasterData::with('typeChassis')->findOrFail($validated['master_data_id']);

        // Ambil ID Chassis langsung dari kolom master_data
        $chassisId = $masterData->c_type_chassis_id;

        // Validasi ekstra: Pastikan Chassis ID benar-benar ada
        if (!$chassisId) {
            return response()->json(['message' => 'Data Master tidak memiliki Type Chassis yang valid.'], 422);
        }

        // 3. Cek File Fisik di Database
        $existingFile = \App\Models\MasterKelistrikanFile::where('c_type_chassis_id', $chassisId)->first();
        $fileId = null;

        if ($existingFile) {
            // KASUS A: File fisik sudah ada -> Pakai ID lama
            $fileId = $existingFile->id;
        } else {
            // KASUS B: File belum ada -> Wajib Upload
            if (!$request->hasFile('gambar_kelistrikan')) {
                return response()->json(['message' => 'File PDF wajib diupload karena belum ada file untuk Chassis ini.'], 422);
            }

            // Upload Logic (Membangun Path)
            // Pastikan relasi induk termuat untuk nama folder
            $masterData->load(['typeEngine', 'merk']);

            $pathData = [
                $masterData->typeEngine->type_engine,
                $masterData->merk->merk,
                $masterData->typeChassis->type_chassis, // Pastikan relasi typeChassis sudah di-load/ada
                'kelistrikan'
            ];

            // Bersihkan nama folder
            $basePath = implode('/', array_map(fn($part) => \Illuminate\Support\Str::slug($part), $pathData));
            $fileName = \Illuminate\Support\Str::slug($masterData->typeChassis->type_chassis) . '_base.pdf';

            $path = $request->file('gambar_kelistrikan')->storeAs($basePath, $fileName, 'master_gambar');

            // Simpan ke tabel File Fisik
            $newFile = \App\Models\MasterKelistrikanFile::create([
                'c_type_chassis_id' => $chassisId, // <-- Ini yang sebelumnya error NULL
                'path_file' => $path
            ]);
            $fileId = $newFile->id;
        }

        // 4. Simpan Data Logis (Deskripsi)
        $gambar = IGambarKelistrikan::create([
            'master_data_id' => $validated['master_data_id'],
            'master_kelistrikan_file_id' => $fileId,
            'deskripsi' => \Illuminate\Support\Str::upper($validated['deskripsi']),
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
    public function checkFileStatus($chassisId)
    {
        $existingFile = MasterKelistrikanFile::where('c_type_chassis_id', $chassisId)->first();

        return response()->json([
            'exists' => $existingFile !== null,
            'filename' => $existingFile ? basename($existingFile->path_file) : null
        ]);
    }
}
