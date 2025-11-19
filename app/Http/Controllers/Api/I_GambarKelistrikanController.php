<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ATypeEngine;
use App\Models\BMerk;
use App\Models\CTypeChassis;
use App\Models\IGambarKelistrikan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class I_GambarKelistrikanController extends Controller
{
    public function index(Request $request)
    {
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

        // 1. Query utama pada tabel i_gambar_kelistrikan
        $query = IGambarKelistrikan::query()
            // 2. JOIN langsung ke masing-masing tabel independen
            ->join('a_type_engines', 'i_gambar_kelistrikan.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'i_gambar_kelistrikan.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'i_gambar_kelistrikan.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->select('i_gambar_kelistrikan.*');

        // 3. Eager load relasi (langsung ke A, B, C)
        $query->with(['typeEngine', 'merk', 'typeChassis']);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('i_gambar_kelistrikan.deskripsi', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortColumn = match ($sortBy) {
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'deskripsi' => 'i_gambar_kelistrikan.deskripsi',
            default => 'i_gambar_kelistrikan.updated_at',
        };
        $query->orderBy($sortColumn, $sortDirection);

        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'a_type_engine_id' => 'required|integer|exists:a_type_engines,id',
            'b_merk_id' => 'required|integer|exists:b_merks,id',
            'c_type_chassis_id' => 'required|integer|exists:c_type_chassis,id',
            'gambar_kelistrikan' => 'required|file|mimes:pdf',
            'deskripsi' => 'required|string|max:255',
        ]);

        // Cek duplikasi kombinasi 3 ID
        $exists = IGambarKelistrikan::where('a_type_engine_id', $validated['a_type_engine_id'])
            ->where('b_merk_id', $validated['b_merk_id'])
            ->where('c_type_chassis_id', $validated['c_type_chassis_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Gambar Kelistrikan untuk kombinasi ini sudah ada.'], 422);
        }

        // Ambil data nama dari tabel independen untuk membuat path folder
        $engine = ATypeEngine::find($validated['a_type_engine_id']);
        $merk = BMerk::find($validated['b_merk_id']);
        $chassis = CTypeChassis::find($validated['c_type_chassis_id']);

        // Path: kelistrikan/{id_chassis} (Sesuai request sebelumnya agar simpel & stabil)
        $basePath = 'kelistrikan/' . $chassis->id;
        $fileName = Str::slug($validated['deskripsi']) . '.pdf';

        $path = $request->file('gambar_kelistrikan')->storeAs($basePath, $fileName, 'master_gambar');

        $gambar = IGambarKelistrikan::create([
            'a_type_engine_id' => $validated['a_type_engine_id'],
            'b_merk_id' => $validated['b_merk_id'],
            'c_type_chassis_id' => $validated['c_type_chassis_id'],
            'path_gambar_kelistrikan' => $path,
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        return response()->json($gambar->load(['typeEngine', 'merk', 'typeChassis']), 201);
    }

    public function update(Request $request, IGambarKelistrikan $gambarKelistrikan)
    {
        $validated = $request->validate(['deskripsi' => 'required|string|max:255']);
        $gambarKelistrikan->update(['deskripsi' => Str::upper($validated['deskripsi'])]);
        return response()->json($gambarKelistrikan->load('typeChassis.merk.typeEngine'));
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
        if (!Storage::disk('master_gambar')->exists($path)) {
            return response()->json(['message' => 'File PDF tidak ditemukan.'], 404);
        }
        $filePath = Storage::disk('master_gambar')->path($path);
        return response()->file($filePath, ['Content-Type' => 'application/pdf']);
    }
}
