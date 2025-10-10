<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use Illuminate\Http\Request;

class ImageStatusController extends Controller
{
    public function index(Request $request)
    {
        // 1. Validasi input dari frontend (untuk paginasi, sort, filter)
        $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|min:1|max:100',
            'sortBy' => 'string|in:updated_at,type_engine,merk,type_chassis,jenis_kendaraan,varian_body',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        // Ambil parameter, berikan nilai default
        $perPage = $request->input('perPage', 25);
        $sortBy = $request->input('sortBy', 'updated_at');
        $sortDirection = $request->input('sortDirection', 'desc');
        $search = $request->input('search', '');

        // 2. Query utama ke Varian Body
        $query = EVarianBody::query();

        // 3. Eager load relasi induk (untuk sorting & filtering) dan cek keberadaan relasi gambar
        $query->with([
            'jenisKendaraan.typeChassis.merk.typeEngine'
        ])
            ->withExists('gambarUtama') // Cek apakah relasi 'gambarUtama' ada (menghasilkan kolom 'gambar_utama_exists' => true/false)
            ->withExists('gambarOptional'); // Cek apakah relasi 'gambarOptional' ada (menghasilkan kolom 'gambar_optional_exists' => true/false)

        // 4. Terapkan filter pencarian jika ada
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('varian_body', 'like', "%{$search}%")
                    ->orWhereHas('jenisKendaraan', function ($q_jk) use ($search) {
                        $q_jk->where('jenis_kendaraan', 'like', "%{$search}%")
                            ->orWhereHas('typeChassis', function ($q_tc) use ($search) {
                                $q_tc->where('type_chassis', 'like', "%{$search}%")
                                    ->orWhereHas('merk', function ($q_m) use ($search) {
                                        $q_m->where('merk', 'like', "%{$search}%")
                                            ->orWhereHas('typeEngine', function ($q_te) use ($search) {
                                                $q_te->where('type_engine', 'like', "%{$search}%");
                                            });
                                    });
                            });
                    });
            });
        }

        // 5. Terapkan sorting
        // Untuk sorting berdasarkan relasi, kita perlu join tabel
        if (in_array($sortBy, ['type_engine', 'merk', 'type_chassis', 'jenis_kendaraan'])) {
            $query->select('e_varian_body.*') // Hindari ambiguitas nama kolom
                ->join('d_jenis_kendaraan', 'e_varian_body.jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
                ->join('c_type_chassis', 'd_jenis_kendaraan.type_chassis_id_placeholder', '=', 'c_type_chassis.id') // Ganti placeholder
                ->join('b_merks', 'c_type_chassis.merk_id_placeholder', '=', 'b_merks.id') // Ganti placeholder
                ->join('a_type_engines', 'b_merks.type_engine_id_placeholder', '=', 'a_type_engines.id') // Ganti placeholder
                ->orderBy(
                    match ($sortBy) {
                        'type_engine' => 'a_type_engines.type_engine',
                        'merk' => 'b_merks.merk',
                        'type_chassis' => 'c_type_chassis.type_chassis',
                        'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
                    },
                    $sortDirection
                );
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }


        // 6. Lakukan paginasi
        return $query->paginate($perPage);
    }
}
