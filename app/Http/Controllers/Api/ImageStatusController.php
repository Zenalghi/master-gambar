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
        // 1. Validasi parameter: Tambahkan 'deskripsi_optional' untuk sorting
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:type_engine,merk,type_chassis,jenis_kendaraan,varian_body,updated_at,deskripsi_optional',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'updated_at'; // Default sort sesuai permintaan
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query utama yang berpusat pada EVarianBody
        $query = \App\Models\EVarianBody::query();

        // 3. Lakukan JOIN yang BENAR untuk sorting dan searching
        // JOIN untuk hirarki induk (Type Engine, Merk, dll.)
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

        // LEFT JOIN untuk mendapatkan tanggal update gambar utama (jika ada)
        $query->leftJoin('g_gambar_utama', 'e_varian_body.id', '=', 'g_gambar_utama.e_varian_body_id');

        // LEFT JOIN untuk mendapatkan deskripsi gambar optional dependen (jika ada)
        // Kita asumsikan satu gambar utama hanya punya satu dependen untuk laporan ini
        $query->leftJoin('h_gambar_optional', function ($join) {
            $join->on('g_gambar_utama.id', '=', 'h_gambar_optional.g_gambar_utama_id')
                ->where('h_gambar_optional.tipe', '=', 'dependen');
        });

        // 4. Pilih kolom secara eksplisit untuk performa dan hindari ambiguitas
        $query->select([
            'e_varian_body.*', // Ambil semua dari varian body
            'g_gambar_utama.updated_at as gambar_utama_updated_at',
            'h_gambar_optional.deskripsi as deskripsi_optional',
        ]);

        // 5. Eager load relasi untuk membentuk struktur JSON yang kaya di frontend
        $query->with(['jenisKendaraan.typeChassis.merk.typeEngine', 'gambarUtama', 'gambarUtama.gambarOptionals']);

        // 6. Terapkan filter pencarian yang efisien
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('e_varian_body.varian_body', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('h_gambar_optional.deskripsi', 'like', "%{$search}%")
                    ->orWhere('g_gambar_utama.updated_at', 'like', "%{$search}%");
            });
        }

        // 7. Terapkan sorting yang lengkap
        $sortColumn = match ($sortBy) {
            'varian_body' => 'e_varian_body.varian_body',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'merk' => 'b_merks.merk',
            'type_engine' => 'a_type_engines.type_engine',
            'deskripsi_optional' => 'deskripsi_optional',
            default => 'g_gambar_utama.updated_at', // Default sort baru (updated at gambar utama)
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 8. Lakukan paginasi
        return $query->paginate($perPage);
    }
}
