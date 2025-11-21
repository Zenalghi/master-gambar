<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMerkRequest;
use App\Http\Requests\UpdateMerkRequest;
use App\Models\BMerk;
use App\Models\MasterData; // <-- Import MasterData untuk validasi hapus
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class B_MerkController extends Controller
{
    /**
     * Menampilkan semua data, diurutkan berdasarkan nama merk A-Z.
     * Kita juga memuat relasi typeEngine agar data induknya ikut terbawa.
     */
    public function index(Request $request)
    {
        // 1. Validasi parameter
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:id,merk,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'id'; // Default sort
        $sortDirection = $validated['sortDirection'] ?? 'asc'; // Default direction
        $search = $validated['search'] ?? '';

        $query = BMerk::query();

        // 3. Terapkan filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('merk', 'like', "%{$search}%")
                    ->orWhere('created_at', 'like', "%{$search}%")
                    ->orWhere('updated_at', 'like', "%{$search}%");
            });
        }

        // 4. Terapkan sorting
        $query->orderBy($sortBy, $sortDirection);

        // 5. Lakukan paginasi
        return $query->paginate($perPage);
    }

    // --- FITUR BARU: List data sampah ---
    public function trash()
    {
        return BMerk::onlyTrashed()->orderBy('deleted_at', 'desc')->get();
    }

    public function store(StoreMerkRequest $request)
    {
        $validated = $request->validated();
        // Hapus logika ID manual, biarkan auto-increment
        $merk = BMerk::create($validated);
        return response()->json($merk, 201);
    }

    public function show(BMerk $merk)
    {
        return $merk;
    }

    public function update(UpdateMerkRequest $request, BMerk $merk)
    {
        $merk->update($request->validated());
        return response()->json($merk); // Tidak perlu load relasi
    }

    public function destroy(BMerk $merk)
    {
        // Soft Delete tidak perlu cek relasi yang ketat
        $merk->delete();
        return response()->json(null, 204);
    }

    // --- FITUR RESTORE ---
    public function restore($id)
    {
        $merk = BMerk::onlyTrashed()->findOrFail($id);
        $merk->restore();
        return response()->json($merk);
    }

    // --- FITUR FORCE DELETE ---
    public function forceDelete($id)
    {
        // Cek apakah data ini dipakai di Master Data (Tabel kombinasi utama)
        // Jika masih dipakai, tolak penghapusan permanen.
        if (MasterData::where('b_merk_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Data tidak bisa dihapus permanen karena masih digunakan di Master Data (Kombinasi).']
            ]);
        }

        $merk = BMerk::onlyTrashed()->findOrFail($id);
        $merk->forceDelete();

        return response()->json(null, 204);
    }
}
