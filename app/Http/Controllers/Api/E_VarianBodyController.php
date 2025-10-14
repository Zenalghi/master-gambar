<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVarianBodyRequest;
use App\Http\Requests\UpdateVarianBodyRequest;
use App\Models\EVarianBody;
use App\Models\TransaksiVarian;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class E_VarianBodyController extends Controller
{
    /**
     * Mengambil semua varian body, diurutkan berdasarkan namanya,
     * dan memuat data induknya.
     */
    public function index(Request $request)
    {
        // 1. Validate parameters
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:id,varian_body,jenis_kendaraan,type_chassis,merk,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Main query with JOINs to all parent tables
        $query = \App\Models\EVarianBody::query()
            ->join('d_jenis_kendaraan', 'e_varian_body.jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->join('c_type_chassis', function ($join) {
                $join->on(DB::raw('SUBSTRING(e_varian_body.jenis_kendaraan_id, 1, 7)'), '=', 'c_type_chassis.id');
            })
            ->join('b_merks', function ($join) {
                $join->on(DB::raw('SUBSTRING(e_varian_body.jenis_kendaraan_id, 1, 4)'), '=', 'b_merks.id');
            })
            ->select('e_varian_body.*'); // Select all columns from the main table

        // 3. Eager load relationships for the JSON response structure
        $query->with('jenisKendaraan.typeChassis.merk.typeEngine');

        // 4. Apply search filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('e_varian_body.id', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.varian_body', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.created_at', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.updated_at', 'like', "%{$search}%");
            });
        }

        // 5. Apply sorting
        $sortColumn = match ($sortBy) {
            'id' => 'e_varian_body.id',
            'varian_body' => 'e_varian_body.varian_body',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'merk' => 'b_merks.merk',
            'created_at' => 'e_varian_body.created_at',
            'updated_at' => 'e_varian_body.updated_at',
            default => 'e_varian_body.updated_at',
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 6. Paginate the results
        return $query->paginate($perPage);
    }

    public function store(StoreVarianBodyRequest $request)
    {
        $varianBody = EVarianBody::create($request->validated());
        return response()->json($varianBody->load('jenisKendaraan.typeChassis.merk.typeEngine'), 201);
    }

    public function show(EVarianBody $varianBody)
    {
        return response()->json($varianBody->load('jenisKendaraan.typeChassis.merk.typeEngine'));
    }

    public function update(UpdateVarianBodyRequest $request, EVarianBody $varianBody)
    {
        $varianBody->update($request->validated());
        return response()->json($varianBody->fresh()->load('jenisKendaraan.typeChassis.merk.typeEngine'));
    }

    public function destroy(EVarianBody $varianBody)
    {
        // --- TAMBAHKAN PROTEKSI BARU ---
        if (TransaksiVarian::where('e_varian_body_id', $varianBody->id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Tidak dapat menghapus Varian Body karena sudah digunakan dalam transaksi.']
            ]);
        }
        // -----------------------------

        // Logika hapus file-file terkait (sudah ada dan benar)
        $varianBody->load(['gambarUtama', 'gambarOptional']);
        // ... (sisa logika hapus file)

        $varianBody->delete();
        return response()->json(null, 204);
    }
}
