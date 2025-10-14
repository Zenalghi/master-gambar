<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeChassisRequest;
use App\Http\Requests\UpdateTypeChassisRequest;
use App\Models\CTypeChassis;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class C_TypeChassisController extends Controller
{
    /**
     * Menampilkan semua data, diurutkan berdasarkan ID.
     * Memuat relasi merk dan typeEngine induknya.
     */
    public function index(Request $request)
    {
        // 1. Validasi parameter dari frontend
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:id,type_chassis,merk,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'asc';
        $search = $validated['search'] ?? '';

        // 2. Query utama: Selalu JOIN ke tabel merk
        $query = \App\Models\CTypeChassis::query()
            // Lakukan JOIN dengan kondisi SUBSTRING berdasarkan ID komposit Anda
            ->join('b_merks', function ($join) {
                $join->on(DB::raw('SUBSTRING(c_type_chassis.id, 1, 4)'), '=', 'b_merks.id');
            })
            ->select('c_type_chassis.*'); // Pilih semua kolom dari c_type_chassis

        // 3. Eager load relasi (tetap dibutuhkan untuk struktur JSON)
        $query->with('merk.typeEngine');

        // 4. Terapkan filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('c_type_chassis.id', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%") // Cari di tabel yang di-join
                    ->orWhere('c_type_chassis.created_at', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.updated_at', 'like', "%{$search}%");
            });
        }

        // 5. Terapkan sorting
        $sortColumn = match ($sortBy) {
            'id' => 'c_type_chassis.id',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'merk' => 'b_merks.merk', // Sort berdasarkan kolom dari tabel yang di-join
            'created_at' => 'c_type_chassis.created_at',
            'updated_at' => 'c_type_chassis.updated_at',
            default => 'c_type_chassis.id',
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 6. Lakukan paginasi
        return $query->paginate($perPage);
    }

    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreTypeChassisRequest $request)
    {
        $validated = $request->validated();
        $merkId = $validated['merk_id'];

        // --- LOGIKA ID OTOMATIS (7 DIGIT) ---
        $lastChassis = CTypeChassis::where('id', 'like', $merkId . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextCode = '001';
        if ($lastChassis) {
            $lastCode = intval(substr($lastChassis->id, 4, 3));
            $nextCodeInt = $lastCode + 1;
            $nextCode = str_pad($nextCodeInt, 3, '0', STR_PAD_LEFT);
        }

        $newId = $merkId . $nextCode;
        // ------------------------------------

        $typeChassis = CTypeChassis::create([
            'id' => $newId,
            'type_chassis' => $validated['type_chassis'],
        ]);

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
        if ($typeChassis->getJenisKendaraanChildren()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Tipe Chassis karena masih memiliki data Jenis Kendaraan.']
            ]);
        }
        $typeChassis->delete();
        return response()->json(null, 204);
    }
}
