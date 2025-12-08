<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeChassisRequest;
use App\Http\Requests\UpdateTypeChassisRequest;
use App\Models\CTypeChassis;
use App\Models\IGambarKelistrikan;
use App\Models\MasterData;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $sortBy = $validated['sortBy'] ?? 'updated_at'; // Default sort
        $sortDirection = $validated['sortDirection'] ?? 'desc'; // Default direction
        $search = $validated['search'] ?? '';

        $query = CTypeChassis::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('type_chassis', 'like', "%{$search}%")
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
        return CTypeChassis::onlyTrashed()->orderBy('deleted_at', 'desc')->get();
    }
    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreTypeChassisRequest $request)
    {
        $validated = $request->validated();
        // Hapus logika ID manual dan merk_id
        $typeChassis = CTypeChassis::create($validated);
        return response()->json($typeChassis, 201);
    }

    public function show(CTypeChassis $typeChassis)
    {
        return $typeChassis;
    }

    public function update(UpdateTypeChassisRequest $request, CTypeChassis $typeChassis)
    {
        $typeChassis->update($request->validated());
        return response()->json($typeChassis);
    }

    public function destroy(CTypeChassis $typeChassis)
    {
        // Soft Delete tidak perlu cek relasi yang ketat
        $typeChassis->delete();
        return response()->json(null, 204);
    }

    // --- FITUR RESTORE ---
    public function restore($id)
    {
        $typeChassis = CTypeChassis::onlyTrashed()->findOrFail($id);
        $typeChassis->restore();
        return response()->json($typeChassis);
    }

    // --- FITUR FORCE DELETE ---
    public function forceDelete($id)
    {
        // Cek apakah data ini dipakai di Master Data
        if (MasterData::where('c_type_chassis_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Data tidak bisa dihapus permanen karena masih digunakan di Master Data (Kombinasi).']
            ]);
        }
        $chassis = CTypeChassis::onlyTrashed()->find($id);
        if ($chassis->fileKelistrikan()->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Data tidak bisa dihapus permanen karena masih memiliki file kelistrikan terkait.']
            ]);
        }

        $typeChassis = CTypeChassis::onlyTrashed()->findOrFail($id);
        $typeChassis->forceDelete();

        return response()->json(null, 204);
    }
}
