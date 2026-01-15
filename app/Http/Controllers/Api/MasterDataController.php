<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMasterDataRequest;
use App\Http\Requests\UpdateMasterDataRequest;
use App\Models\EVarianBody;
use App\Models\MasterData;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MasterDataController extends Controller
{
    /**
     * Menampilkan data master dengan paginasi, filter, dan sort.
     */
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
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query Utama (Eloquent Builder)
        $query = \App\Models\MasterData::query()
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id');

        // --- PERBAIKAN: SUBQUERY UNTUK KELISTRIKAN ---
        // Kita ambil data kelistrikan TERBARU (limit 1) menggunakan addSelect subquery.
        // Cara ini aman dari error Group By dan Duplikasi.

        $query->addSelect([
            'master_data.*', // Pilih semua kolom master data

            // Subquery: ID Kelistrikan Terbaru
            'kelistrikan_id' => \App\Models\IGambarKelistrikan::select('id')
                ->whereColumn('master_data_id', 'master_data.id')
                ->latest() // Ambil yang paling baru dibuat
                ->limit(1),

            // Subquery: Deskripsi Kelistrikan Terbaru
            'kelistrikan_deskripsi' => \App\Models\IGambarKelistrikan::select('deskripsi')
                ->whereColumn('master_data_id', 'master_data.id')
                ->latest()
                ->limit(1),

            // Subquery: File ID (Cek di tabel deskripsi dulu, kalau null cek tabel file fisik)
            // Ini agak kompleks untuk subquery murni, kita sederhanakan:
            // Kita ambil master_kelistrikan_file_id dari deskripsi terbaru.
            'file_kelistrikan_id' => \App\Models\IGambarKelistrikan::select('master_kelistrikan_file_id')
                ->whereColumn('master_data_id', 'master_data.id')
                ->latest()
                ->limit(1)
        ]);

        // 3. Eager Load (Tetap Sama)
        $query->with(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan']);

        // 4. Filter Search (Update logika pencarian kelistrikan)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('master_data.id', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhereHas('gambarKelistrikan', function ($qKelistrikan) use ($search) {
                        $qKelistrikan->where('deskripsi', 'like', "%{$search}%");
                    });
            });
        }

        // 5. Sorting
        $sortColumn = match ($sortBy) {
            'id' => 'master_data.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'created_at' => 'master_data.created_at',
            'updated_at' => 'master_data.updated_at',
            'kelistrikan_deskripsi' => 'kelistrikan_deskripsi',
            default => 'master_data.updated_at',
        };

        $query->orderBy($sortColumn, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * Menyimpan data master baru.
     */
    public function store(StoreMasterDataRequest $request)
    {
        $masterData = MasterData::create($request->validated());
        $masterData->load(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan']);
        return response()->json($masterData, 201);
    }

    /**
     * Memperbarui data master.
     */
    // Ganti $masterData menjadi $masterDatum agar Laravel bisa menyambungkannya (Binding)
    public function update(UpdateMasterDataRequest $request, MasterData $masterDatum)
    {
        $masterDatum->update($request->validated());

        // Ambil data fresh dari DB beserta relasinya
        $updatedData = $masterDatum->fresh()->load(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan']);

        return response()->json($updatedData);
    }

    // Ubah $masterData menjadi $masterDatum
    public function destroy(MasterData $masterDatum)
    {
        // 1. Cek Proteksi Relasi
        // if (\App\Models\EVarianBody::where('master_data_id', $masterDatum->id)->withTrashed()->exists()) {
        //     throw ValidationException::withMessages([
        //         'general' => ['Tidak dapat menghapus Master Data ini karena masih digunakan oleh Varian Body.']
        //     ]);
        // }

        // 2. Lakukan Soft Delete pada variabel yang benar
        $masterDatum->delete();

        return response()->noContent();
    }

    // --- FITUR RECYCLE BIN ---
    public function trash()
    {
        // Ambil data yang dihapus beserta relasinya untuk ditampilkan
        return MasterData::onlyTrashed()
            ->with(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan'])
            ->orderBy('deleted_at', 'desc')
            ->get();
    }

    public function restore($id)
    {
        $masterData = MasterData::onlyTrashed()->findOrFail($id);
        $masterData->restore();
        return response()->json($masterData);
    }

    public function forceDelete($id)
    {
        // Cek apakah Master Data ini dipakai di Varian Body (walaupun sudah di soft delete)
        // Kita gunakan query raw atau withTrashed untuk memastikannya
        if (EVarianBody::where('master_data_id', $id)->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Data tidak bisa dihapus permanen karena masih digunakan oleh Varian Body (aktif/sampah).']
            ]);
        }

        $masterData = MasterData::onlyTrashed()->findOrFail($id);
        $masterData->forceDelete();

        return response()->json(null, 204);
    }
}
