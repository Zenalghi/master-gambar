<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody; // <-- Pastikan EVarianBody di-import
use App\Models\HGambarOptional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class H_GambarOptionalController extends Controller
{
    /**
     * Menampilkan data gambar optional dengan paginasi, filter, dan sort
     * (Sekarang menggunakan relasi MasterData)
     */
    public function index(Request $request)
    {
        // 1. Validasi parameter
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:type_engine,merk,type_chassis,jenis_kendaraan,tipe,varian_body,deskripsi,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query utama (berpusat pada h_gambar_optional)
        $query = HGambarOptional::query()
            ->join('e_varian_body', 'h_gambar_optional.e_varian_body_id', '=', 'e_varian_body.id')
            ->join('master_data', 'e_varian_body.master_data_id', '=', 'master_data.id')
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->select('h_gambar_optional.*'); // <-- Selalu select tabel utama

        // 3. Eager load relasi (untuk struktur JSON)
        // Kita load relasi VarianBody, yang di dalamnya sudah me-load MasterData
        $query->with('varianBody.masterData.typeEngine', 'varianBody.masterData.merk', 'varianBody.masterData.typeChassis', 'varianBody.masterData.jenisKendaraan');

        // 4. Terapkan filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('h_gambar_optional.deskripsi', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.tipe', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.varian_body', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.created_at', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.updated_at', 'like', "%{$search}%");
            });
        }

        // 5. Terapkan sorting
        $sortColumn = match ($sortBy) {
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'varian_body' => 'e_varian_body.varian_body',
            'tipe' => 'h_gambar_optional.tipe',
            'deskripsi' => 'h_gambar_optional.deskripsi',
            'created_at' => 'h_gambar_optional.created_at',
            'updated_at' => 'h_gambar_optional.updated_at',
            default => 'h_gambar_optional.updated_at',
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 6. Lakukan paginasi
        return $query->paginate($perPage);
    }

    /**
     * Menyimpan gambar optional baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipe' => 'required|in:independen,paket',
            'deskripsi' => 'required|string|max:255',
            'gambar_optional' => 'required|file|mimes:pdf',
            'e_varian_body_id' => 'required_if:tipe,independen|exists:e_varian_body,id',
            'g_gambar_utama_id' => [
                'required_if:tipe,paket',
                'exists:g_gambar_utama,id',
                Rule::unique('h_gambar_optional', 'g_gambar_utama_id')
                    ->where('tipe', 'paket')
                    ->whereNull('deleted_at'),
            ],
        ]);

        $tipe = $validated['tipe'];
        $createData = [
            'tipe' => $tipe,
            'deskripsi' => Str::upper($validated['deskripsi']),
        ];

        $varianBody = null;

        if ($tipe === 'independen') {
            // Ambil Varian Body
            $varianBody = \App\Models\EVarianBody::with('masterData')->find($validated['e_varian_body_id']);
            $createData['e_varian_body_id'] = $varianBody->id;
            $subfolder = 'independen';
        } else { // tipe === 'paket'
            // Ambil Gambar Utama & Varian Body-nya
            $gambarUtama = \App\Models\GGambarUtama::with('varianBody.masterData')->find($validated['g_gambar_utama_id']);
            $varianBody = $gambarUtama->varianBody;

            $createData['g_gambar_utama_id'] = $validated['g_gambar_utama_id'];
            $createData['e_varian_body_id'] = $varianBody->id;
            $subfolder = 'paket';
        }

        // Path tetap menggunakan ID Master Data untuk struktur folder
        $basePath = $varianBody->master_data_id . '/' . $varianBody->id . '/' . $subfolder;
        $fileName = Str::slug($validated['deskripsi']) . '.pdf';

        $path = $request->file('gambar_optional')->storeAs($basePath, $fileName, 'master_gambar');
        $createData['path_gambar_optional'] = $path;

        $gambarOptional = HGambarOptional::create($createData);

        // Load relasi untuk response
        $gambarOptional->load('varianBody.masterData');

        return response()->json($gambarOptional, 201);
    }

    /**
     * Memperbarui deskripsi gambar optional.
     */
    public function update(Request $request, HGambarOptional $gambarOptional)
    {
        $validated = $request->validate([
            'deskripsi' => 'required|string|max:255',
        ]);

        $gambarOptional->update([
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        // Ambil kembali data dengan relasi yang benar
        $updatedItem = $gambarOptional->fresh()->load('varianBody.masterData.typeEngine', 'varianBody.masterData.merk', 'varianBody.masterData.typeChassis', 'varianBody.masterData.jenisKendaraan');

        return response()->json($updatedItem);
    }

    /**
     * Menghapus (Soft Delete) gambar optional.
     */
    public function destroy(HGambarOptional $gambarOptional)
    {
        // File fisik tidak dihapus saat soft delete
        // if ($gambarOptional->path_gambar_optional && Storage::disk('master_gambar')->exists($gambarOptional->path_gambar_optional)) {
        //     Storage::disk('master_gambar')->delete($gambarOptional->path_gambar_optional);
        // }

        $gambarOptional->delete(); // Lakukan Soft Delete

        return response()->noContent();
    }

    /**
     * Menampilkan file PDF.
     */
    public function showPdf(HGambarOptional $gambarOptional)
    {
        $path = $gambarOptional->path_gambar_optional;

        if (!Storage::disk('master_gambar')->exists($path)) {
            return response()->json(['message' => 'File PDF tidak ditemukan.'], 404);
        }

        $filePath = Storage::disk('master_gambar')->path($path);
        return response()->file($filePath, ['Content-Type' => 'application/pdf']);
    }
}
