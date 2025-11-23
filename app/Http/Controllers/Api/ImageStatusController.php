<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImageStatusController extends Controller
{
    /**
     * Menampilkan laporan status gambar dengan paginasi, filter, dan sort
     * yang sesuai dengan arsitektur MasterData.
     */
    // app/Http/Controllers/Api/ImageStatusController.php

    public function index(Request $request)
    {
        // 1. Validasi
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:id,type_engine,merk,type_chassis,jenis_kendaraan,varian_body,updated_at,deskripsi_optional',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query utama
        $query = EVarianBody::query()
            ->join('master_data', 'e_varian_body.master_data_id', '=', 'master_data.id')
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->leftJoin('g_gambar_utama', 'e_varian_body.id', '=', 'g_gambar_utama.e_varian_body_id')
            ->leftJoin('h_gambar_optional', function ($join) {
                $join->on('g_gambar_utama.id', '=', 'h_gambar_optional.g_gambar_utama_id')
                    ->where('h_gambar_optional.tipe', '=', 'paket');
            });

        // 3. Select
        $query->select([
            'e_varian_body.*',
            'a_type_engines.type_engine',
            'b_merks.merk',
            'c_type_chassis.type_chassis',
            'd_jenis_kendaraan.jenis_kendaraan',
            'g_gambar_utama.updated_at as gambar_utama_updated_at',
            'h_gambar_optional.deskripsi as deskripsi_optional',
        ]);

        // 4. Eager load
        $query->with([
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan',
            'gambarUtama.gambarOptionals'
        ]);

        // 5. Search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('e_varian_body.id', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.varian_body', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.deskripsi', 'like', "%{$search}%")
                    ->orWhere('g_gambar_utama.updated_at', 'like', "%{$search}%");
            });
        }

        // 6. Sorting
        $sortColumn = match ($sortBy) {
            'id' => 'e_varian_body.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'varian_body' => 'e_varian_body.varian_body',
            'deskripsi_optional' => 'deskripsi_optional',
            'updated_at' => 'gambar_utama_updated_at',
            default => 'e_varian_body.id',
        };

        // Null-safe sorting
        if (in_array($sortBy, ['updated_at', 'deskripsi_optional'])) {
            $query->orderByRaw(DB::raw("$sortColumn IS NULL $sortDirection, $sortColumn $sortDirection"));
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        // 7. Pagination + FIX search/sort ter-reset
        return $query
            ->paginate($perPage)
            ->appends([
                'search' => $search,
                'sortBy' => $sortBy,
                'sortDirection' => $sortDirection,
                'perPage' => $perPage
            ]);
    }
}
