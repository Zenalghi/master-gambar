<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVarianBodyRequest;
use App\Http\Requests\UpdateVarianBodyRequest;
use Illuminate\Support\Facades\Storage;
use App\Models\EVarianBody;

class E_VarianBodyController extends Controller
{
    public function index()
    {
        // Mengambil semua varian body dengan data induknya
        return response()->json(EVarianBody::with('jenisKendaraan')->orderBy('id')->get());
    }

    public function store(StoreVarianBodyRequest $request)
    {
        $varianBody = EVarianBody::create($request->validated());
        return response()->json($varianBody, 201);
    }

    public function show(EVarianBody $varianBody)
    {
        return response()->json($varianBody->load('jenisKendaraan'));
    }

    public function update(UpdateVarianBodyRequest $request, EVarianBody $varianBody)
    {
        $varianBody->update($request->validated());
        return response()->json($varianBody);
    }

    public function destroy(EVarianBody $varianBody)
    {
        // 2. Eager load relasi ke gambar utama dan optional untuk efisiensi
        $varianBody->load(['gambarUtama', 'gambarOptional']);

        $folderPath = null;

        // 3. Hapus file-file Gambar Utama jika ada
        if ($varianBody->gambarUtama) {
            // Ambil path folder dari salah satu file
            $folderPath = dirname($varianBody->gambarUtama->path_gambar_utama);

            Storage::disk('master_gambar')->delete([
                $varianBody->gambarUtama->path_gambar_utama,
                $varianBody->gambarUtama->path_gambar_terurai,
                $varianBody->gambarUtama->path_gambar_kontruksi,
            ]);
        }

        // 4. Hapus file Gambar Optional jika ada
        if ($varianBody->gambarOptional) {
            // Ambil path folder jika belum didapat dari gambar utama
            if (!$folderPath) {
                $folderPath = dirname($varianBody->gambarOptional->path_gambar_optional);
            }
            Storage::disk('master_gambar')->delete($varianBody->gambarOptional->path_gambar_optional);
        }

        // 5. Hapus seluruh folder Varian Body yang sekarang sudah kosong
        if ($folderPath) {
            Storage::disk('master_gambar')->deleteDirectory($folderPath);
        }

        // 6. Hapus record Varian Body dari database
        // (Record di g_gambar_utama dan h_gambar_optional akan terhapus otomatis karena onDelete('cascade'))
        $varianBody->delete();

        return response()->json(null, 204);
    }
}
