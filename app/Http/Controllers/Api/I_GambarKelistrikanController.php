<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IGambarKelistrikan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class I_GambarKelistrikanController extends Controller
{
    public function index(Request $request)
    {
        // 1. Validasi parameter
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

        // 2. Query utama: JOIN ke semua tabel induk
        $query = \App\Models\IGambarKelistrikan::query()
            ->join('c_type_chassis', 'i_gambar_kelistrikan.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('b_merks', 'i_gambar_kelistrikan.b_merk_id', '=', 'b_merks.id')
            ->join('a_type_engines', 'i_gambar_kelistrikan.a_type_engine_id', '=', 'a_type_engines.id')
            ->select('i_gambar_kelistrikan.*');

        // 3. Eager load relasi untuk struktur JSON
        $query->with('typeChassis.merk.typeEngine');

        // 4. Terapkan filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('i_gambar_kelistrikan.deskripsi', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.id', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.id', 'like', "%{$search}%")
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
        $validated = $request->validate([
            'c_type_chassis_id' => 'required|exists:c_type_chassis,id|unique:i_gambar_kelistrikan,c_type_chassis_id', // <-- Tambahkan unique
            'gambar_kelistrikan' => 'required|file|mimes:pdf',
            'deskripsi' => 'required|string|max:255',
        ]);

        // 1. Ambil data Type Chassis beserta semua relasi induknya
        $typeChassis = \App\Models\CTypeChassis::with('merk.typeEngine')
            ->find($validated['c_type_chassis_id']);

        // 2. Bangun path file
        $pathParts = [
            $typeChassis->merk->typeEngine->type_engine,
            $typeChassis->merk->merk,
            $typeChassis->type_chassis,
        ];
        $basePath = implode('/', array_map(fn($part) => Str::slug($part), $pathParts));
        $fileName = Str::slug($validated['deskripsi']) . '.pdf';
        $path = $request->file('gambar_kelistrikan')->storeAs($basePath, $fileName, 'master_gambar');

        // 3. Buat entri baru dengan menyertakan SEMUA ID induk
        $gambarKelistrikan = IGambarKelistrikan::create([
            'a_type_engine_id' => $typeChassis->merk->typeEngine->id,
            'b_merk_id' => $typeChassis->merk->id,
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
}
