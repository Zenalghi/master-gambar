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
    public function index(Request $request)
    {
        // 1. Validasi
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:50,100',
            'sortBy' => 'nullable|string',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
            'id' => 'nullable|string',
            'type_engine' => 'nullable|string',
            'merk' => 'nullable|string',
            'type_chassis' => 'nullable|string',
            'jenis_kendaraan' => 'nullable|string',
            'varian_body' => 'nullable|string',
            'created_at' => 'nullable|string',
            'updated_at' => 'nullable|string',
            'deskripsi_optional' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 50;
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

        // 3. Select dengan LOGIKA KOMPARASI TANGGAL
        $query->select([
            'e_varian_body.*',
            'a_type_engines.type_engine',
            'b_merks.merk',
            'c_type_chassis.type_chassis',
            'c_type_chassis.merek_dagang',
            'c_type_chassis.nomor_sut',
            'd_jenis_kendaraan.jenis_kendaraan',
            'g_gambar_utama.created_at as gambar_utama_created_at',
            'g_gambar_utama.updated_at as gambar_utama_updated_at',
            'h_gambar_optional.deskripsi as deskripsi_optional',

            // --- LOGIKA UTAMA: Bandingkan Tanggal ---
            // Jika Optional NULL, ambil Utama.
            // Jika Optional ADA, bandingkan mana yang lebih besar (terbaru).
            DB::raw('
                CASE 
                    WHEN g_gambar_utama.updated_at IS NULL THEN NULL
                    WHEN h_gambar_optional.updated_at IS NULL THEN g_gambar_utama.updated_at
                    WHEN h_gambar_optional.updated_at > g_gambar_utama.updated_at THEN h_gambar_optional.updated_at
                    ELSE g_gambar_utama.updated_at
                END as latest_updated_at
            ')
        ]);

        // 4. Eager load
        $query->with([
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan',
            'gambarUtama.gambarOptionals'
        ]);

        // 5. Advanced Filter Map
        $filterMap = [
            'id' => 'e_varian_body.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'nomor_sut' => 'c_type_chassis.nomor_sut',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'varian_body' => 'e_varian_body.varian_body',
            'created_at' => 'g_gambar_utama.created_at',
            'deskripsi_optional' => 'h_gambar_optional.deskripsi',
        ];

        foreach ($filterMap as $key => $column) {
            if ($request->filled($key)) {
                if ($key === 'type_chassis') {
                    $val = $request->input($key);
                    $query->where(function ($q) use ($val) {
                        $q->where('c_type_chassis.type_chassis', 'like', "%{$val}%")
                          ->orWhere('c_type_chassis.merek_dagang', 'like', "%{$val}%");
                    });
                } else {
                    $query->where($column, 'like', '%' . $request->input($key) . '%');
                }
            }
        }

        // Khusus updated_at (cek di gambar_utama dan gambar_optional)
        if ($request->filled('updated_at')) {
            $upd = $request->input('updated_at');
            $query->where(function ($q) use ($upd) {
                $q->where('g_gambar_utama.updated_at', 'like', "%{$upd}%")
                  ->orWhere('h_gambar_optional.updated_at', 'like', "%{$upd}%");
            });
        }

        // 6. Search Global
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('e_varian_body.id', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.varian_body', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.merek_dagang', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.nomor_sut', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.deskripsi', 'like', "%{$search}%")
                    ->orWhere('g_gambar_utama.created_at', 'like', "%{$search}%")
                    ->orWhere('g_gambar_utama.updated_at', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.updated_at', 'like', "%{$search}%");
            });
        }

        // 7. Sorting Mapping
        $sortColumn = match ($sortBy) {
            'id' => 'e_varian_body.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'nomor_sut' => 'c_type_chassis.nomor_sut',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'varian_body' => 'e_varian_body.varian_body',
            'deskripsi_optional' => 'h_gambar_optional.deskripsi',
            'created_at' => 'g_gambar_utama.created_at',
            'updated_at' => 'latest_updated_at',

            default => 'e_varian_body.id',
        };

        // 8. Penerapan Sorting
        // Kita gunakan orderBy biasa karena 'latest_updated_at' sudah berupa kolom kalkulasi yang bersih
        if ($sortBy === 'updated_at') {
            // Khusus tanggal, pastikan null (belum upload) ada di bawah/atas sesuai kebutuhan
            if ($sortDirection === 'desc') {
                // Terbaru paling atas (Null di bawah)
                $query->orderByRaw("latest_updated_at IS NULL ASC, latest_updated_at DESC");
            } else {
                // Terlama paling atas
                $query->orderByRaw("latest_updated_at IS NULL DESC, latest_updated_at ASC");
            }
        } elseif ($sortBy === 'created_at') {
            // LOGIKA SORTING CREATED AT (Mirip updated_at)
            if ($sortDirection === 'desc') {
                $query->orderByRaw("g_gambar_utama.created_at IS NULL ASC, g_gambar_utama.created_at DESC");
            } else {
                $query->orderByRaw("g_gambar_utama.created_at IS NULL DESC, g_gambar_utama.created_at ASC");
            }
        } elseif ($sortBy === 'nomor_sut' || $sortBy === 'merek_dagang' || $sortBy === 'jenis_tipe' || $sortBy === 'deskripsi_optional') {
            $query->orderByRaw("({$sortColumn} IS NULL OR {$sortColumn} = '') ASC");
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        // 9. Pagination
        $paginator = $query->paginate($perPage);

        // 10. Transformasi Data (Opsional, agar Frontend menerima field yang konsisten)
        // Kita timpa field 'gambar_utama_updated_at' dengan hasil kalkulasi agar frontend menampilkan tanggal terbaru
        $paginator->getCollection()->transform(function ($item) {
            // Timpa nilai ini agar UI menampilkan tanggal komparasi
            $item->gambar_utama_updated_at = $item->latest_updated_at;
            return $item;
        });

        return $paginator->appends($request->query());
    }
}
