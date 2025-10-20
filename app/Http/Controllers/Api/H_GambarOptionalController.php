<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HGambarOptional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class H_GambarOptionalController extends Controller
{
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

        // 2. Query utama: JOIN ke semua tabel induk
        $query = \App\Models\HGambarOptional::query()
            ->join('e_varian_body', 'h_gambar_optional.e_varian_body_id', '=', 'e_varian_body.id')
            ->join('d_jenis_kendaraan', 'h_gambar_optional.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->join('c_type_chassis', 'h_gambar_optional.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('b_merks', 'h_gambar_optional.b_merk_id', '=', 'b_merks.id')
            ->join('a_type_engines', 'h_gambar_optional.a_type_engine_id', '=', 'a_type_engines.id')
            ->select('h_gambar_optional.*');

        // 3. Eager load relasi (untuk konsistensi struktur JSON)
        $query->with('varianBody.jenisKendaraan.typeChassis.merk.typeEngine');

        // 4. Terapkan filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('h_gambar_optional.deskripsi', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.id', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.id', 'like', "%{$search}%")
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipe' => 'required|in:independen,paket',
            'deskripsi' => 'required|string|max:255',
            'gambar_optional' => 'required|file|mimes:pdf',
            'e_varian_body_id' => 'required_if:tipe,independen|exists:e_varian_body,id',
            'g_gambar_utama_id' => 'required_if:tipe,paket|exists:g_gambar_utama,id',
        ]);

        $tipe = $validated['tipe'];
        $pathData = []; // Untuk menyimpan data path
        $createData = [
            'tipe' => $tipe,
            'deskripsi' => Str::upper($validated['deskripsi']),
        ];

        if ($tipe === 'independen') {
            $varianBody = \App\Models\EVarianBody::with('jenisKendaraan.typeChassis.merk.typeEngine')
                ->find($validated['e_varian_body_id']);

            $pathData = [
                $varianBody->jenisKendaraan->typeChassis->merk->typeEngine->type_engine,
                $varianBody->jenisKendaraan->typeChassis->merk->merk,
                $varianBody->jenisKendaraan->typeChassis->type_chassis,
                $varianBody->jenisKendaraan->jenis_kendaraan,
                $varianBody->varian_body,
                'independen' // Tambahkan subfolder
            ];

            $createData += [
                'a_type_engine_id' => $varianBody->jenisKendaraan->typeChassis->merk->typeEngine->id,
                'b_merk_id' => $varianBody->jenisKendaraan->typeChassis->merk->id,
                'c_type_chassis_id' => $varianBody->jenisKendaraan->typeChassis->id,
                'd_jenis_kendaraan_id' => $varianBody->jenisKendaraan->id,
                'e_varian_body_id' => $validated['e_varian_body_id'],
            ];
        } else { // tipe === 'paket'
            $gambarUtama = \App\Models\GGambarUtama::with('varianBody.jenisKendaraan.typeChassis.merk.typeEngine')
                ->find($validated['g_gambar_utama_id']);

            $pathData = [
                $gambarUtama->varianBody->jenisKendaraan->typeChassis->merk->typeEngine->type_engine,
                $gambarUtama->varianBody->jenisKendaraan->typeChassis->merk->merk,
                $gambarUtama->varianBody->jenisKendaraan->typeChassis->type_chassis,
                $gambarUtama->varianBody->jenisKendaraan->jenis_kendaraan,
                $gambarUtama->varianBody->varian_body,
                'paket' // Tambahkan subfolder
            ];

            $createData += [
                'g_gambar_utama_id' => $validated['g_gambar_utama_id'],
                'a_type_engine_id' => $gambarUtama->varianBody->jenisKendaraan->typeChassis->merk->typeEngine->id,
                'b_merk_id' => $gambarUtama->varianBody->jenisKendaraan->typeChassis->merk->id,
                'c_type_chassis_id' => $gambarUtama->varianBody->jenisKendaraan->typeChassis->id,
                'd_jenis_kendaraan_id' => $gambarUtama->varianBody->jenisKendaraan->id,
                'e_varian_body_id' => $gambarUtama->varianBody->id,
            ];
        }

        $basePath = implode('/', array_map(fn($part) => Str::slug($part), $pathData));
        $fileName = Str::slug($validated['deskripsi']) . '.pdf';
        $path = $request->file('gambar_optional')->storeAs($basePath, $fileName, 'master_gambar');
        $createData['path_gambar_optional'] = $path;

        $gambarOptional = HGambarOptional::create($createData);

        return response()->json($gambarOptional, 201);
    }

    public function update(Request $request, HGambarOptional $gambarOptional) // <-- DIUBAH DI SINI
    {
        $validated = $request->validate([
            'deskripsi' => 'required|string|max:255',
        ]);

        // Lakukan update pada variabel yang benar
        $gambarOptional->update([ // <-- DIUBAH DI SINI
            'deskripsi' => Str::upper($validated['deskripsi']),
        ]);

        // Ambil kembali data berdasarkan ID dari variabel yang benar
        $updatedItem = HGambarOptional::with('varianBody.jenisKendaraan.typeChassis.merk.typeEngine')
            ->findOrFail($gambarOptional->id); // <-- DIUBAH DI SINI

        return response()->json($updatedItem);
    }

    public function destroy(HGambarOptional $gambarOptional) // <-- DIUBAH DI SINI
    {
        // Gunakan variabel yang benar untuk mengambil path
        if ($gambarOptional->path_gambar_optional && Storage::disk('master_gambar')->exists($gambarOptional->path_gambar_optional)) { // <-- DIUBAH DI SINI
            Storage::disk('master_gambar')->delete($gambarOptional->path_gambar_optional); // <-- DIUBAH DI SINI
        }

        // Hapus data dengan variabel yang benar
        $gambarOptional->delete(); // <-- DIUBAH DI SINI

        return response()->noContent();
    }

    public function showPdf(HGambarOptional $gambarOptional)
    {
        $path = $gambarOptional->path_gambar_optional;

        // Cek apakah file ada di dalam disk 'master_gambar'
        if (!Storage::disk('master_gambar')->exists($path)) {
            return response()->json(['message' => 'File PDF tidak ditemukan.'], 404);
        }

        // Ambil path lengkap ke file
        $filePath = Storage::disk('master_gambar')->path($path);

        // Kirim file sebagai respons untuk diunduh/ditampilkan
        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
