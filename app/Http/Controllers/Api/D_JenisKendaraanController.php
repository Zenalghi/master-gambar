<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJenisKendaraanRequest;
use App\Http\Requests\UpdateJenisKendaraanRequest;
use App\Models\DJenisKendaraan;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\EVarianBody;

class D_JenisKendaraanController extends Controller
{
    /**
     * Menampilkan semua data, diurutkan berdasarkan nama jenis kendaraan.
     */
    public function index(Request $request)
    {
        // 1. Validasi parameter
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:id,jenis_kendaraan,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'id'; // Default sort
        $sortDirection = $validated['sortDirection'] ?? 'asc'; // Default direction
        $search = $validated['search'] ?? '';

        // 2. Query utama (HANYA ke tabel d_jenis_kendaraan)
        $query = \App\Models\DJenisKendaraan::query();

        // 3. Terapkan filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('created_at', 'like', "%{$search}%")
                    ->orWhere('updated_at', 'like', "%{$search}%");
            });
        }

        // 4. Terapkan sorting
        $query->orderBy($sortBy, $sortDirection);

        // 5. Lakukan paginasi
        return $query->paginate($perPage);
    }

    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreJenisKendaraanRequest $request)
    {
        $validated = $request->validated();
        $jenisKendaraan = DJenisKendaraan::create($validated);
        return response()->json($jenisKendaraan, 201);
    }

    public function show(DJenisKendaraan $jenisKendaraan)
    {
        return response()->json($jenisKendaraan->load('typeChassis.merk.typeEngine'));
    }

    public function update(UpdateJenisKendaraanRequest $request, DJenisKendaraan $jenisKendaraan)
    {
        $jenisKendaraan->update($request->validated());
        return response()->json($jenisKendaraan->fresh()->load('typeChassis.merk.typeEngine'));
    }

    public function destroy(DJenisKendaraan $jenisKendaraan)
    {
        // Cek relasi ke E_VarianBody (sekarang cek berdasarkan foreign key integer)
        if (EVarianBody::where('d_jenis_kendaraan_id', $jenisKendaraan->id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Jenis Kendaraan karena masih memiliki data Varian Body.']
            ]);
        }

        $jenisKendaraan->delete(); // Soft delete
        return response()->json(null, 204);
    }
}
