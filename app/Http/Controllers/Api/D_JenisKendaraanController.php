<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJenisKendaraanRequest;
use App\Http\Requests\UpdateJenisKendaraanRequest;
use App\Models\DJenisKendaraan;
use App\Models\MasterData; // Import MasterData for dependency check
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $query = DJenisKendaraan::query();

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

    // --- NEW FEATURE: Trash List ---
    public function trash()
    {
        return DJenisKendaraan::onlyTrashed()->orderBy('deleted_at', 'desc')->get();
    }
    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreJenisKendaraanRequest $request)
    {
        $validated = $request->validated();
        // Create directly, no custom ID logic needed
        $jenisKendaraan = DJenisKendaraan::create($validated);
        return response()->json($jenisKendaraan, 201);
    }

    public function show(DJenisKendaraan $jenisKendaraan)
    {
        return $jenisKendaraan;
    }

    public function update(UpdateJenisKendaraanRequest $request, DJenisKendaraan $jenisKendaraan)
    {
        $jenisKendaraan->update($request->validated());
        return response()->json($jenisKendaraan);
    }

    public function destroy(DJenisKendaraan $jenisKendaraan)
    {
        // Soft delete doesn't require strict dependency check
        $jenisKendaraan->delete();
        return response()->json(null, 204);
    }

    // --- NEW FEATURE: Restore ---
    public function restore($id)
    {
        $jenisKendaraan = DJenisKendaraan::onlyTrashed()->findOrFail($id);
        $jenisKendaraan->restore();
        return response()->json($jenisKendaraan);
    }

    // --- NEW FEATURE: Force Delete ---
    public function forceDelete($id)
    {
        // Check if used in MasterData (since it's part of the independent master structure)
        if (MasterData::where('d_jenis_kendaraan_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Data tidak bisa dihapus permanen karena masih digunakan di Master Data (Kombinasi).']
            ]);
        }

        $jenisKendaraan = DJenisKendaraan::onlyTrashed()->findOrFail($id);
        $jenisKendaraan->forceDelete();

        return response()->json(null, 204);
    }
}
