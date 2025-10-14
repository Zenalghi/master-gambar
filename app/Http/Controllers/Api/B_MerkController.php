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
        // 1. Validasi: Tambahkan kolom tanggal ke 'sortBy'
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:id,merk,type_engine,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'asc';
        $search = $validated['search'] ?? '';

        // 2. Query utama: Selalu JOIN ke tabel type_engine
        $query = \App\Models\BMerk::query()
            // Lakukan JOIN dengan kondisi SUBSTRING
            ->join('a_type_engines', function ($join) {
                $join->on(DB::raw('SUBSTRING(b_merks.id, 1, 2)'), '=', 'a_type_engines.id');
            })
            ->select('b_merks.*'); // Tetap pilih semua kolom dari b_merks

        // Eager load relasi (tetap dibutuhkan untuk struktur JSON)
        $query->with('typeEngine');

        // 4. Terapkan filter pencarian yang lebih sederhana
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('b_merks.id', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.created_at', 'like', "%{$search}%")
                    ->orWhere('b_merks.updated_at', 'like', "%{$search}%");
            });
        }

        // 5. Terapkan sorting yang lebih lengkap
        $sortColumn = match ($sortBy) {
            'id' => 'b_merks.id',
            'merk' => 'b_merks.merk',
            'type_engine' => 'a_type_engines.type_engine',
            'created_at' => 'b_merks.created_at',
            'updated_at' => 'b_merks.updated_at',
            default => 'b_merks.id',
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 6. Lakukan paginasi
        return $query->paginate($perPage);
    }

    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreMerkRequest $request)
    {
        $validated = $request->validated();
        $typeEngineId = $validated['type_engine_id'];

        // --- LOGIKA ID OTOMATIS (4 DIGIT) ---
        // 1. Cari Merk terakhir yang memiliki type_engine_id yang sama.
        $lastMerk = BMerk::where('id', 'like', $typeEngineId . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextCode = '01'; // Default jika ini adalah merk pertama untuk type engine tsb.
        if ($lastMerk) {
            // 2. Ambil 2 digit terakhir dari ID, ubah ke integer, tambah 1.
            $lastCode = intval(substr($lastMerk->id, 2, 2));
            $nextCodeInt = $lastCode + 1;
            // 3. Format kembali menjadi 2 digit.
            $nextCode = str_pad($nextCodeInt, 2, '0', STR_PAD_LEFT);
        }

        // 4. Gabungkan untuk membuat ID komposit baru (contoh: '01' . '02' -> '0102').
        $newId = $typeEngineId . $nextCode;
        // ------------------------------------

        $merk = BMerk::create([
            'id' => $newId,
            'merk' => $validated['merk'],
        ]);

        // Muat relasi agar respons JSON berisi data typeEngine
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
        if (CTypeChassis::where('id', 'like', $merk->id . '%')->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Merk karena masih memiliki data Tipe Chassis.']
            ]);
        }

        $merk->delete();
        return response()->json(null, 204);
    }
}
