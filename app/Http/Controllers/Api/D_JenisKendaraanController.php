<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJenisKendaraanRequest;
use App\Http\Requests\UpdateJenisKendaraanRequest;
use App\Models\DJenisKendaraan;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'sortBy' => 'nullable|string|in:id,jenis_kendaraan,type_chassis,merk,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'asc';
        $search = $validated['search'] ?? '';

        // 2. Query utama dengan JOIN ke semua tabel induk
        $query = \App\Models\DJenisKendaraan::query()
            ->join('c_type_chassis', function ($join) {
                $join->on(DB::raw('SUBSTRING(d_jenis_kendaraan.id, 1, 7)'), '=', 'c_type_chassis.id');
            })
            ->join('b_merks', function ($join) {
                $join->on(DB::raw('SUBSTRING(d_jenis_kendaraan.id, 1, 4)'), '=', 'b_merks.id');
            })
            ->select('d_jenis_kendaraan.*'); // Pilih semua kolom dari tabel utama

        // 3. Eager load relasi (tetap dibutuhkan untuk struktur JSON)
        $query->with('typeChassis.merk.typeEngine');

        // 4. Terapkan filter pencarian
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('d_jenis_kendaraan.id', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%") // Cari di tabel join
                    ->orWhere('b_merks.merk', 'like', "%{$search}%") // Cari di tabel join
                    ->orWhere('d_jenis_kendaraan.created_at', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.updated_at', 'like', "%{$search}%");
            });
        }

        // 5. Terapkan sorting
        $sortColumn = match ($sortBy) {
            'id' => 'd_jenis_kendaraan.id',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'merk' => 'b_merks.merk',
            'created_at' => 'd_jenis_kendaraan.created_at',
            'updated_at' => 'd_jenis_kendaraan.updated_at',
            default => 'd_jenis_kendaraan.id',
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 6. Lakukan paginasi
        return $query->paginate($perPage);
    }

    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreJenisKendaraanRequest $request)
    {
        $validated = $request->validated();
        $typeChassisId = $validated['type_chassis_id'];

        // --- LOGIKA ID OTOMATIS (9 DIGIT) ---
        $lastJenis = DJenisKendaraan::where('id', 'like', $typeChassisId . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextCode = 'AA'; // Default jika ini adalah jenis pertama
        if ($lastJenis) {
            $lastCode = substr($lastJenis->id, 7, 2); // Ambil 2 karakter terakhir
            $nextCode = ++$lastCode; // Increment karakter (e.g., 'AA' -> 'AB')
        }

        $newId = $typeChassisId . $nextCode;
        // ------------------------------------

        $jenisKendaraan = DJenisKendaraan::create([
            'id' => $newId,
            'jenis_kendaraan' => $validated['jenis_kendaraan'],
        ]);

        return response()->json($jenisKendaraan->load('typeChassis.merk.typeEngine'), 201);
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
        if ($jenisKendaraan->varianBody()->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Jenis Kendaraan karena masih memiliki data Varian Body.']
            ]);
        }

        $jenisKendaraan->delete();
        return response()->json(null, 204);
    }
}
