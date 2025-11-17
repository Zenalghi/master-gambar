<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVarianBodyRequest;
use App\Http\Requests\UpdateVarianBodyRequest;
use App\Models\EVarianBody;
use App\Models\GGambarUtama; // <-- Tambahkan
use App\Models\HGambarOptional; // <-- Tambahkan
use App\Models\TransaksiVarian;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request; // <-- Tambahkan

class E_VarianBodyController extends Controller
{
    /**
     * Mengambil semua varian body dengan server-side processing.
     */
    public function index(Request $request)
    {
        // 1. Validasi
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:id,varian_body,type_engine,merk,type_chassis,jenis_kendaraan,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'updated_at'; // Default sort
        $sortDirection = $validated['sortDirection'] ?? 'desc'; // Default direction
        $search = $validated['search'] ?? '';

        // 2. Query utama
        $query = \App\Models\EVarianBody::query()
            ->join('master_data', 'e_varian_body.master_data_id', '=', 'master_data.id')
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->select('e_varian_body.*'); // Penting!

        // 3. Eager load relasi
        $query->with('masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan');

        // 4. Terapkan filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('e_varian_body.id', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.varian_body', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.created_at', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.updated_at', 'like', "%{$search}%");
            });
        }

        // 5. Terapkan sorting
        $sortColumn = match ($sortBy) {
            'id' => 'e_varian_body.id',
            'varian_body' => 'e_varian_body.varian_body',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'created_at' => 'e_varian_body.created_at',
            'updated_at' => 'e_varian_body.updated_at',
            default => 'e_varian_body.updated_at',
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 6. Lakukan paginasi
        return $query->paginate($perPage);
    }

    public function store(StoreVarianBodyRequest $request)
    {
        $varianBody = EVarianBody::create($request->validated());
        $varianBody->load('masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan');
        return response()->json($varianBody, 201);
    }

    public function show(EVarianBody $varianBody)
    {
        $varianBody->load('masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan');
        return response()->json($varianBody);
    }

    public function update(UpdateVarianBodyRequest $request, EVarianBody $varianBody)
    {
        $varianBody->update($request->validated());
        $varianBody->fresh()->load('masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan');
        return response()->json($varianBody);
    }

    public function destroy(EVarianBody $varianBody)
    {
        // Cek semua relasi anak
        if (
            // TransaksiVarian::where('e_varian_body_id', $varianBody->id)->exists() ||
            GGambarUtama::where('e_varian_body_id', $varianBody->id)->exists() ||
            HGambarOptional::where('e_varian_body_id', $varianBody->id)->exists()
        ) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Varian Body karena sudah digunakan oleh Transaksi atau Gambar Master.']
            ]);
        }

        $varianBody->delete();
        return response()->json(null, 204);
    }
}
