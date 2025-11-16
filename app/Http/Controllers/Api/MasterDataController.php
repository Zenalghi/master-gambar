<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMasterDataRequest;
use App\Http\Requests\UpdateMasterDataRequest;
use App\Models\EVarianBody;
use App\Models\MasterData;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MasterDataController extends Controller
{
    /**
     * Menampilkan data master dengan paginasi, filter, dan sort.
     */
    public function index(Request $request)
    {
        // 1. Validasi
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:type_engine,merk,type_chassis,jenis_kendaraan',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'asc';
        $search = $validated['search'] ?? '';

        // 2. Query utama dengan JOIN ke semua tabel master
        $query = MasterData::query()
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->select('master_data.*'); // <-- Penting!

        // 3. Eager load relasi (untuk struktur JSON)
        $query->with(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan']);

        // 4. Terapkan filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%");
            });
        }

        // 5. Terapkan sorting
        $sortColumn = match ($sortBy) {
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            default => 'master_data.id',
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 6. Lakukan paginasi
        return $query->paginate($perPage);
    }

    /**
     * Menyimpan data master baru.
     */
    public function store(StoreMasterDataRequest $request)
    {
        $masterData = MasterData::create($request->validated());
        $masterData->load(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan']);
        return response()->json($masterData, 201);
    }

    /**
     * Memperbarui data master.
     */
    public function update(UpdateMasterDataRequest $request, MasterData $masterData)
    {
        $masterData->update($request->validated());
        $masterData->fresh()->load(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan']);
        return response()->json($masterData);
    }

    /**
     * Menghapus (Soft Delete) data master.
     */
    public function destroy(MasterData $masterData)
    {
        // Proteksi: Cek apakah data master ini masih dipakai oleh Varian Body
        if (EVarianBody::where('master_data_id', $masterData->id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Master Data ini karena masih digunakan oleh Varian Body.']
            ]);
        }

        $masterData->delete(); // Soft delete
        return response()->noContent();
    }
}
