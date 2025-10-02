<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IGambarKelistrikan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class I_GambarKelistrikanController extends Controller
{
    public function index()
    {
        return IGambarKelistrikan::with('typeChassis.merk.typeEngine')
            ->orderBy('id')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'c_type_chassis_id' => 'required|exists:c_type_chassis,id',
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
