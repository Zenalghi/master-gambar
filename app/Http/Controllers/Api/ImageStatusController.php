<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImageStatusController extends Controller
{
    public function index(Request $request)
    {
        // 1. Validasi (Tetap Sama)
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:50,100',
            'sortBy' => 'nullable|string',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 50;
        $sortBy = $validated['sortBy'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query Utama
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
            })
            ->withCount(['gambarUtama']); // Penting untuk cek ketersediaan data

        // 3. Select (DITAMBAHKAN PATH FILE AGAR TOMBOL PREVIEW DINAMIS)
        $query->select([
            'e_varian_body.id', // Sebutkan satu per satu field penting agar aman
            'e_varian_body.varian_body',
            'e_varian_body.master_data_id', // <--- WAJIB ADA INI
            'e_varian_body.created_at',
            'e_varian_body.updated_at',
            'a_type_engines.type_engine',
            'b_merks.merk',
            'c_type_chassis.type_chassis',
            'd_jenis_kendaraan.jenis_kendaraan',

            // --- TAMBAHAN PENTING: ID GAMBAR UTAMA (Alias biar gak bentrok) ---
            'g_gambar_utama.id as gambar_utama_id',

            'g_gambar_utama.created_at as gambar_utama_created_at',
            'g_gambar_utama.updated_at as gambar_utama_updated_at',
            'g_gambar_utama.path_gambar_utama',
            'g_gambar_utama.path_gambar_terurai',
            'g_gambar_utama.path_gambar_kontruksi',
            'h_gambar_optional.path_gambar_optional as path_gambar_paket',
            'h_gambar_optional.deskripsi as deskripsi_optional',

            // Logika Tanggal (Tetap Sama)
            DB::raw('
                CASE 
                    WHEN g_gambar_utama.updated_at IS NULL THEN NULL
                    WHEN h_gambar_optional.updated_at IS NULL THEN g_gambar_utama.updated_at
                    WHEN h_gambar_optional.updated_at > g_gambar_utama.updated_at THEN h_gambar_optional.updated_at
                    ELSE g_gambar_utama.updated_at
                END as latest_updated_at
            ')
        ]);

        // 4. Eager load (Tetap)
        $query->with([
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan',
            // 'gambarUtama.gambarOptionals' // Tidak perlu eager load ini jika kita sudah join manual di atas
        ]);

        // 5. Search (Tetap)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('e_varian_body.id', 'like', "%{$search}%")
                    ->orWhere('e_varian_body.varian_body', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.deskripsi', 'like', "%{$search}%");
            });
        }

        // 6. Sorting (Tetap)
        $sortColumn = match ($sortBy) {
            'id' => 'e_varian_body.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'varian_body' => 'e_varian_body.varian_body',
            'deskripsi_optional' => 'h_gambar_optional.deskripsi',
            'created_at' => 'g_gambar_utama.created_at',
            'updated_at' => 'latest_updated_at',
            default => 'e_varian_body.id',
        };

        if ($sortBy === 'updated_at') {
            if ($sortDirection === 'desc') {
                $query->orderByRaw("latest_updated_at IS NULL ASC, latest_updated_at DESC");
            } else {
                $query->orderByRaw("latest_updated_at IS NULL DESC, latest_updated_at ASC");
            }
        } elseif ($sortBy === 'created_at') {
            if ($sortDirection === 'desc') {
                $query->orderByRaw("g_gambar_utama.created_at IS NULL ASC, g_gambar_utama.created_at DESC");
            } else {
                $query->orderByRaw("g_gambar_utama.created_at IS NULL DESC, g_gambar_utama.created_at ASC");
            }
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }
        // 7. Pagination & Transformasi (UPDATE BAGIAN INI)
        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function ($item) {
            // A. Override updated_at global
            $item->gambar_utama_updated_at = $item->latest_updated_at;

            // B. REKONSTRUKSI OBJEK 'gambar_utama' (Tetap sama seperti sebelumnya)
            if ($item->gambar_utama_id) {
                $item->gambar_utama = [
                    'id' => $item->gambar_utama_id,
                    'e_varian_body_id' => $item->id,
                    'path_gambar_utama' => $item->path_gambar_utama,
                    'path_gambar_terurai' => $item->path_gambar_terurai,
                    'path_gambar_kontruksi' => $item->path_gambar_kontruksi,
                    'created_at' => $item->gambar_utama_created_at,
                    'updated_at' => $item->gambar_utama_updated_at,
                    // Masukkan juga gambar optionals (paket) ke dalam array ini jika model flutter mengharapkannya
                    // Tapi biasanya model ImageStatus punya field terpisah untuk optional.
                    // Jika model GGambarUtama di Flutter punya list 'gambar_optionals', kita perlu format array kosong/isi.
                    'gambar_optionals' => $item->path_gambar_paket ? [
                        [
                            'tipe' => 'paket',
                            'path_gambar_optional' => $item->path_gambar_paket,
                            'deskripsi' => $item->deskripsi_optional
                        ]
                    ] : []
                ];
            } else {
                $item->gambar_utama = null;
            }

            // C. REKONSTRUKSI OBJEK 'master_data' (PERBAIKAN UTAMA)
            // Karena Flutter VarianBody.fromJson mengharapkan json['master_data']
            // Dan MasterData.fromJson mengharapkan json['type_engine'], json['merk'], dll.

            $item->master_data = [
                'id' => $item->master_data_id, // Pastikan master_data_id ada di e_varian_body
                'created_at' => null, // Opsional jika tidak di-select
                'updated_at' => null, // Opsional jika tidak di-select

                // Nested Objects untuk TypeEngine, Merk, dll
                'type_engine' => ['id' => 0, 'type_engine' => $item->type_engine],
                'merk' => ['id' => 0, 'merk' => $item->merk],
                'type_chassis' => ['id' => 0, 'type_chassis' => $item->type_chassis],
                'jenis_kendaraan' => ['id' => 0, 'jenis_kendaraan' => $item->jenis_kendaraan],
            ];

            // D. Logika Status
            $isComplete = $item->gambar_utama_count > 0;
            $item->status_gambar = $isComplete ? 'Lengkap' : 'Belum Lengkap';
            $item->color_status = $isComplete ? 'green' : 'red';

            return $item;
        });

        return $paginator->appends([
            'search' => $search,
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
            'perPage' => $perPage
        ]);
    }
}
