<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImageStatusController extends Controller
{ // app/Http/Controllers/Api/ImageStatusController.php

    public function index(Request $request)
    {
        // 1. Validasi: Tambahkan semua kolom yang bisa di-sort
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:type_engine,merk,type_chassis,jenis_kendaraan,varian_body,gambar_utama_updated_at,gambar_optional_updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        // Default sort baru sesuai permintaan Anda
        $sortBy = $validated['sortBy'] ?? 'gambar_utama_updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query utama ke EVarianBody
        $query = \App\Models\EVarianBody::query();

        // 3. Lakukan JOIN yang BENAR untuk sorting dan searching
        // Join untuk hirarki induk (Type Engine, Merk, dll.)
        $query->join('d_jenis_kendaraan', 'e_varian_body.jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->join('c_type_chassis', function ($join) {
                $join->on(DB::raw('SUBSTRING(d_jenis_kendaraan.id, 1, 7)'), '=', 'c_type_chassis.id');
            })
            ->join('b_merks', function ($join) {
                $join->on(DB::raw('SUBSTRING(d_jenis_kendaraan.id, 1, 4)'), '=', 'b_merks.id');
            })
            ->join('a_type_engines', function ($join) {
                $join->on(DB::raw('SUBSTRING(d_jenis_kendaraan.id, 1, 2)'), '=', 'a_type_engines.id');
            });

        // LEFT JOIN untuk mendapatkan tanggal update gambar (jika ada)
        $query->leftJoin('g_gambar_utama', 'e_varian_body.id', '=', 'g_gambar_utama.e_varian_body_id');

        // Subquery untuk mendapatkan tanggal update gambar optional independen TERBARU
        $latestOptionalSubquery = \App\Models\HGambarOptional::select('e_varian_body_id', DB::raw('MAX(updated_at) as latest_updated_at'))
            ->where('tipe', 'independen')
            ->groupBy('e_varian_body_id');

        $query->leftJoinSub($latestOptionalSubquery, 'latest_optionals', function ($join) {
            $join->on('e_varian_body.id', '=', 'latest_optionals.e_varian_body_id');
        });

        // 4. Pilih kolom secara eksplisit dan buat alias untuk tanggal update
        $query->select([
            'e_varian_body.*',
            'g_gambar_utama.updated_at as gambar_utama_updated_at',
            'latest_optionals.latest_updated_at as gambar_optional_updated_at',
        ]);

        // 5. Eager load relasi untuk struktur JSON yang benar di frontend
        $query->with([
            'jenisKendaraan.typeChassis.merk.typeEngine',
            'gambarUtama',
            'latestGambarOptional'
        ]);

        // 6. Terapkan filter pencarian yang efisien
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('e_varian_body.varian_body', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    // Cari juga berdasarkan tanggal
                    ->orWhere('g_gambar_utama.updated_at', 'like', "%{$search}%")
                    ->orWhere('latest_optionals.latest_updated_at', 'like', "%{$search}%");
            });
        }

        // 7. Terapkan sorting yang lengkap
        $sortColumn = match ($sortBy) {
            'varian_body' => 'e_varian_body.varian_body',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'merk' => 'b_merks.merk',
            'type_engine' => 'a_type_engines.type_engine',
            'gambar_utama_updated_at' => 'gambar_utama_updated_at',
            'gambar_optional_updated_at' => 'gambar_optional_updated_at',
            default => 'gambar_utama_updated_at', // Default sort baru
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 8. Lakukan paginasi
        return $query->paginate($perPage);
    }
}
