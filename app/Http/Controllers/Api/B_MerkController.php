<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMerkRequest;
use App\Http\Requests\UpdateMerkRequest;
use App\Models\BMerk;
use App\Models\CTypeChassis;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

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
        $sortBy = $validated['sortBy'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'asc';
        $search = $validated['search'] ?? '';

        // 2. Query utama (HANYA ke tabel b_merks)
        $query = \App\Models\BMerk::query();

        // 3. Terapkan filter pencarian (HANYA di kolom b_merks)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('merk', 'like', "%{$search}%")
                    ->orWhere('created_at', 'like', "%{$search}%")
                    ->orWhere('updated_at', 'like', "%{$search}%");
            });
        }

        // 4. Terapkan sorting (HANYA di kolom b_merks)
        $query->orderBy($sortBy, $sortDirection);

        // 5. Lakukan paginasi
        return $query->paginate($perPage);
    }

    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreMerkRequest $request)
    {
        $validated = $request->validated();

        // --- HAPUS SEMUA LOGIKA ID OTOMATIS (4 DIGIT) ---

        $merk = BMerk::create($validated);

        return response()->json($merk->load('typeEngine'), 201);
    }

    public function show(BMerk $merk)
    {
        return $merk->load('typeEngine');
    }

    public function update(UpdateMerkRequest $request, BMerk $merk)
    {
        $merk->update($request->validated());
        return response()->json($merk->fresh()->load('typeEngine'));
    }

    public function destroy(BMerk $merk)
    {
        // Cek relasi ke C_TypeChassis (sekarang cek berdasarkan foreign key integer)
        if (CTypeChassis::where('b_merk_id', $merk->id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Merk karena masih memiliki data Tipe Chassis.']
            ]);
        }

        $merk->delete(); // Ini akan melakukan soft delete
        return response()->json(null, 204);
    }
}
