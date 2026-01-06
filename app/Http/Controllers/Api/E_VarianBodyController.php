<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVarianBodyRequest;
use App\Http\Requests\UpdateVarianBodyRequest;
use App\Models\EVarianBody;
use App\Models\GGambarUtama;
use App\Models\HGambarOptional;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class E_VarianBodyController extends Controller
{
    /**
     * Mengambil semua varian body dengan server-side processing.
     */ public function index(Request $request)
    {
        // 1. Validasi Parameter
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:50,100',
            'sortBy' => 'nullable|string|in:id,varian_body,type_engine,merk,type_chassis,jenis_kendaraan,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
            // Filter optional jika dibutuhkan dropdown
            'master_data_id' => 'nullable|integer',
        ]);

        $perPage = $validated['perPage'] ?? 50;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query utama
        $query = EVarianBody::query()
            ->join('master_data', 'e_varian_body.master_data_id', '=', 'master_data.id')
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->select('e_varian_body.*');

        // 3. Eager load relasi MASTER DATA (Ini kuncinya!)
        $query->with('masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan');

        // 4. Filter Search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('e_varian_body.varian_body', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%");
            });
        }

        // Filter khusus jika dropdown butuh filter by master_data_id
        if ($request->has('master_data_id')) {
            $query->where('e_varian_body.master_data_id', $request->master_data_id);
        }

        // 5. Sorting
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

        return $query->paginate($perPage);
    }

    public function store(StoreVarianBodyRequest $request)
    {
        $varianBody = EVarianBody::create($request->validated());
        return response()->json($varianBody->load('masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan'), 201);
    }

    public function update(UpdateVarianBodyRequest $request, EVarianBody $varianBody)
    {
        $varianBody->update($request->validated());

        // --- PERBAIKAN BUG EDIT ---
        // Kita harus me-assign hasil load kembali ke variabel $varianBody
        // Jika tidak, JSON response akan berisi objek lama yang belum di-load relasinya
        $varianBody = $varianBody->fresh()->load([
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan'
        ]);

        return response()->json($varianBody);
    }
    
    public function show(EVarianBody $varianBody)
    {
        return response()->json($varianBody->load('masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan'));
    }


    public function destroy(EVarianBody $varianBody)
    {
        // Untuk Soft Delete, kita tidak perlu terlalu ketat.
        // Cukup hapus. Proteksi ketat ada di Force Delete.
        $varianBody->delete();
        return response()->json(null, 204);
    }

    // --- FITUR RECYCLE BIN ---
    public function trash()
    {
        return EVarianBody::onlyTrashed()
            ->with('masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan')
            ->orderBy('deleted_at', 'desc')
            ->get();
    }

    public function restore($id)
    {
        $varianBody = EVarianBody::onlyTrashed()->findOrFail($id);
        $varianBody->restore();
        return response()->json($varianBody);
    }

    public function forceDelete($id)
    {
        // Cek Gambar Utama
        if (GGambarUtama::where('e_varian_body_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Data tidak bisa dihapus permanen karena memiliki Gambar Utama. Hapus Gambar Utama terlebih dahulu.']
            ]);
        }
        // Cek Gambar Optional
        if (HGambarOptional::where('e_varian_body_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Data tidak bisa dihapus permanen karena memiliki Gambar Optional. Hapus Gambar Optional terlebih dahulu.']
            ]);
        }

        $varianBody = EVarianBody::onlyTrashed()->findOrFail($id);
        $varianBody->forceDelete();

        return response()->json(null, 204);
    }
}
