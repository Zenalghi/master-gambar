<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeChassisRequest;
use App\Http\Requests\UpdateTypeChassisRequest;
use App\Models\CTypeChassis;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DJenisKendaraan;

class C_TypeChassisController extends Controller
{
    /**
     * Menampilkan semua data, diurutkan berdasarkan ID.
     * Memuat relasi merk dan typeEngine induknya.
     */
    public function index(Request $request)
    {
        // 1. Validasi parameter
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:id,type_chassis,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'asc';
        $search = $validated['search'] ?? '';

        // 2. Query utama (HANYA ke tabel c_type_chassis)
        $query = \App\Models\CTypeChassis::query();

        // 3. Terapkan filter pencarian (HANYA di kolom c_type_chassis)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('type_chassis', 'like', "%{$search}%")
                    ->orWhere('created_at', 'like', "%{$search}%")
                    ->orWhere('updated_at', 'like', "%{$search}%");
            });
        }

        // 4. Terapkan sorting (HANYA di kolom c_type_chassis)
        $query->orderBy($sortBy, $sortDirection);

        // 5. Lakukan paginasi
        return $query->paginate($perPage);
    }

    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreTypeChassisRequest $request)
    {
        $validated = $request->validated();

        // --- HAPUS SEMUA LOGIKA ID OTOMATIS (7 DIGIT) ---

        $typeChassis = CTypeChassis::create($validated);

        return response()->json($typeChassis->load('merk.typeEngine'), 201);
    }

    public function show(CTypeChassis $typeChassis)
    {
        return response()->json($typeChassis->load('merk.typeEngine'));
    }

    public function update(UpdateTypeChassisRequest $request, CTypeChassis $typeChassis)
    {
        $typeChassis->update($request->validated());
        return response()->json($typeChassis->fresh()->load('merk.typeEngine'));
    }

    public function destroy(CTypeChassis $typeChassis)
    {
        // Cek relasi ke D_JenisKendaraan (sekarang cek berdasarkan foreign key integer)
        if (DJenisKendaraan::where('c_type_chassis_id', $typeChassis->id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Tipe Chassis karena masih memiliki data Jenis Kendaraan.']
            ]);
        }
        $typeChassis->delete(); // Soft delete
        return response()->json(null, 204);
    }
}
