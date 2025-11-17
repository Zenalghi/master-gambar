<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IGambarKelistrikan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class I_GambarKelistrikanController extends Controller
{
    public function index(Request $request)
    {
        // 1. Validasi
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:type_engine,merk,type_chassis,deskripsi,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query utama: JOIN ke Type Chassis dan induk-induknya
        $query = \App\Models\IGambarKelistrikan::query()
            ->join('c_type_chassis', 'i_gambar_kelistrikan.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('b_merks', 'c_type_chassis.b_merk_id', '=', 'b_merks.id') // Asumsi relasi baru
            ->join('a_type_engines', 'b_merks.a_type_engine_id', '=', 'a_type_engines.id') // Asumsi relasi baru
            ->select('i_gambar_kelistrikan.*');

        // 3. Eager load relasi (untuk struktur JSON)
        $query->with('typeChassis.merk.typeEngine');

        // 4. Terapkan filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('i_gambar_kelistrikan.deskripsi', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('i_gambar_kelistrikan.created_at', 'like', "%{$search}%")
                    ->orWhere('i_gambar_kelistrikan.updated_at', 'like', "%{$search}%");
            });
        }

        // 5. Terapkan sorting
        $sortColumn = match ($sortBy) {
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'deskripsi' => 'i_gambar_kelistrikan.deskripsi',
            'created_at' => 'i_gambar_kelistrikan.created_at',
            'updated_at' => 'i_gambar_kelistrikan.updated_at',
            default => 'i_gambar_kelistrikan.updated_at',
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 6. Lakukan paginasi
        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        // 1. Validasi: c_type_chassis_id sekarang integer
        $validated = $request->validate([
            'c_type_chassis_id' => [
                'required',
                'integer',
                'exists:c_type_chassis,id',
                // Pastikan unik dan belum di soft-delete
                Rule::unique('i_gambar_kelistrikan')->whereNull('deleted_at'),
            ],
            'gambar_kelistrikan' => 'required|file|mimes:pdf',
            'deskripsi' => 'required|string|max:255',
        ]);

        // 2. Ambil data Type Chassis beserta relasi induknya
        $typeChassis = \App\Models\CTypeChassis::with('merk.typeEngine')
            ->find($validated['c_type_chassis_id']);

        // 3. Bangun path file baru
        $pathData = [
            $typeChassis->merk->typeEngine->type_engine,
            $typeChassis->merk->merk,
            $typeChassis->type_chassis,
            'kelistrikan' // Subfolder baru
        ];
        $basePath = implode('/', array_map(fn($part) => Str::slug($part), $pathData));
        $fileName = Str::slug($validated['deskripsi']) . '.pdf';
        $path = $request->file('gambar_kelistrikan')->storeAs($basePath, $fileName, 'master_gambar');

        // 4. Buat entri baru (tanpa ID induk A dan B, karena tidak perlu)
        $gambarKelistrikan = IGambarKelistrikan::create([
            'c_type_chassis_id' => $validated['c_type_chassis_id'],
            'path_gambar_kelistrikan' => $path,
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        // Muat relasi untuk respons JSON
        return response()->json($gambarKelistrikan->load('typeChassis.merk.typeEngine'), 201);
    }

    public function update(Request $request, IGambarKelistrikan $gambarKelistrikan)
    {
        $validated = $request->validate([
            // 'gambar_kelistrikan' => 'sometimes|file|mimes:pdf',
            'deskripsi' => 'sometimes|string|max:255',
        ]);
        $gambarKelistrikan->update([
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        $updatedGambarKelistrikan = IGambarKelistrikan::with('typeChassis.merk.typeEngine')->find($gambarKelistrikan->id);
        return response()->json($updatedGambarKelistrikan, 200);

        // Jika ada file baru, hapus file lama dan simpan file baru
        // if ($request->hasFile('gambar_kelistrikan')) {
        //     // Hapus file lama
        //     if (Storage::disk('master_gambar')->exists($gambarKelistrikan->path_gambar_kelistrikan)) {
        //         Storage::disk('master_gambar')->delete($gambarKelistrikan->path_gambar_kelistrikan);
        //     }

        //     // Simpan file baru
        //     $typeChassis = \App\Models\CTypeChassis::with('merk.typeEngine')
        //         ->find($gambarKelistrikan->c_type_chassis_id);

        //     $pathParts = [
        //         $typeChassis->merk->typeEngine->type_engine,
        //         $typeChassis->merk->merk,
        //         $typeChassis->type_chassis,
        //     ];
        //     $basePath = implode('/', array_map(fn($part) => Str::slug($part), $pathParts));
        //     $fileName = Str::slug($validated['deskripsi'] ?? $gambarKelistrikan->deskripsi) . '.pdf';
        //     $path = $request->file('gambar_kelistrikan')->storeAs($basePath, $fileName, 'master_gambar');

        //     $validated['path_gambar_kelistrikan'] = $path;
        // }

        // Perbarui entri database
        // if (isset($validated['deskripsi'])) {
        //     $validated['deskripsi'] = Str::upper($validated['deskripsi']);
        // }
        // $gambarKelistrikan->update($validated);

        // return response()->json($gambarKelistrikan->load('typeChassis.merk.typeEngine'), 200);
    }

    public function destroy(IGambarKelistrikan $gambarKelistrikan)
    {
        // Hapus file dari storage
        if ($gambarKelistrikan->path_gambar_kelistrikan && Storage::disk('master_gambar')->exists($gambarKelistrikan->path_gambar_kelistrikan)) {
            Storage::disk('master_gambar')->delete($gambarKelistrikan->path_gambar_kelistrikan);
        }

        // Hapus entri database
        $gambarKelistrikan->delete();

        return response()->noContent();
    }

    public function showPdf(IGambarKelistrikan $gambarKelistrikan)
    {
        $path = $gambarKelistrikan->path_gambar_kelistrikan;

        // Check if the file exists in the specified disk
        if (!Storage::disk('master_gambar')->exists($path)) {
            return response()->json(['message' => 'File PDF tidak ditemukan.'], 404);
        }

        // Get the full path to the file
        $filePath = Storage::disk('master_gambar')->path($path);

        // Return the file as a downloadable response
        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
