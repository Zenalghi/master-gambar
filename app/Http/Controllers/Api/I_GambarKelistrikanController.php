<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CTypeChassis;
use App\Models\IGambarKelistrikan; // Tabel Deskripsi
use App\Models\MasterKelistrikanFile; // Tabel File Fisik
use App\Models\MasterData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class I_GambarKelistrikanController extends Controller
{
    // ==========================================
    // BAGIAN 1: MANAJEMEN FILE (GUDANG FILE)
    // ==========================================

    /**
     * Menampilkan daftar FILE fisik per Chassis.
     */
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

        $query = MasterKelistrikanFile::query()
            ->join('c_type_chassis', 'master_kelistrikan_files.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('b_merks', 'c_type_chassis.b_merk_id', '=', 'b_merks.id')
            ->join('a_type_engines', 'b_merks.a_type_engine_id', '=', 'a_type_engines.id')
            ->select('master_kelistrikan_files.*');

        $query->with('chassis.merk.typeEngine');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('master_kelistrikan_files.id', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('master_kelistrikan_files.created_at', 'like', "%{$search}%")
                    ->orWhere('master_kelistrikan_files.updated_at', 'like', "%{$search}%")
                ;
            });
        }

        //sorting
        $sortColumn = match ($sortBy) {
            'id' => 'master_kelistrikan_files.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'created_at' => 'master_kelistrikan_files.created_at',
            'updated_at' => 'master_kelistrikan_files.updated_at',
            default => 'master_kelistrikan_files.' . $sortBy,
        };
        $query->orderBy($sortColumn, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * Upload File Baru untuk Chassis tertentu.
     */
    public function storeFile(Request $request)
    {
        $validated = $request->validate([
            'c_type_chassis_id' => 'required|integer|exists:c_type_chassis,id|unique:master_kelistrikan_files,c_type_chassis_id',
            'gambar_kelistrikan' => 'required|file|mimes:pdf',
        ]);

        $chassis = CTypeChassis::with('merk.typeEngine')->findOrFail($validated['c_type_chassis_id']);

        // Build Path
        $pathData = [
            $chassis->merk->typeEngine->type_engine,
            $chassis->merk->merk,
            $chassis->type_chassis,
            'kelistrikan'
        ];
        $basePath = implode('/', array_map(fn($part) => Str::slug($part), $pathData));
        $fileName = Str::slug($chassis->type_chassis) . '_base.pdf';

        $path = $request->file('gambar_kelistrikan')->storeAs($basePath, $fileName, 'master_gambar');

        $file = MasterKelistrikanFile::create([
            'c_type_chassis_id' => $chassis->id,
            'path_file' => $path,
        ]);

        return response()->json($file, 201);
    }

    public function destroyFile($id)
    {
        $file = MasterKelistrikanFile::findOrFail($id);

        // Hapus fisik
        if (Storage::disk('master_gambar')->exists($file->path_file)) {
            Storage::disk('master_gambar')->delete($file->path_file);
        }

        // Hapus record (ini akan men-trigger cascade delete ke deskripsi via DB constraint)
        $file->delete();

        return response()->noContent();
    }


    // ==========================================
    // BAGIAN 2: MANAJEMEN DESKRIPSI (MASTER DATA)
    // ==========================================

    /**
     * Menyimpan/Update Deskripsi untuk Master Data tertentu.
     */
    public function storeDeskripsi(Request $request)
    {
        $validated = $request->validate([
            'master_data_id' => 'required|integer|exists:master_data,id',
            'deskripsi' => 'required|string|max:255',
        ]);

        $masterData = MasterData::findOrFail($validated['master_data_id']);

        // 1. Cari File Fisik berdasarkan Chassis Master Data
        $fileFisik = MasterKelistrikanFile::where('c_type_chassis_id', $masterData->c_type_chassis_id)->first();

        if (!$fileFisik) {
            return response()->json(['message' => 'File PDF belum tersedia untuk Chassis ini. Silakan upload di menu Gambar Kelistrikan.'], 422);
        }

        // 2. Simpan/Update Deskripsi
        $gambar = IGambarKelistrikan::updateOrCreate(
            ['master_data_id' => $masterData->id],
            [
                'master_kelistrikan_file_id' => $fileFisik->id,
                'deskripsi' => Str::upper($validated['deskripsi']),
            ]
        );

        return response()->json($gambar, 200);
    }

    // ... method showPdf tetap sama (bisa diakses via IGambarKelistrikan atau MasterKelistrikanFile)
    public function showPdf(IGambarKelistrikan $gambarKelistrikan)
    {
        // ... (logika ambil path dari relasi fileKelistrikan) ...
        $path = $gambarKelistrikan->fileKelistrikan->path_file ?? null;

        if (!$path || !Storage::disk('master_gambar')->exists($path)) {
            return response()->json(['message' => 'File PDF tidak ditemukan.'], 404);
        }
        $filePath = Storage::disk('master_gambar')->path($path);
        return response()->file($filePath, ['Content-Type' => 'application/pdf']);
    }
}
