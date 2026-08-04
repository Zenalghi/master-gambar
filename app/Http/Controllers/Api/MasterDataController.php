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
        // 1. Validasi
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

        // 2. Query Utama
        // Kita gunakan Eloquent murni dengan Eager Loading dan Subquery
        // HINDARI JOIN MANUAL JIKA TIDAK PERLU UNTUK MENGHINDARI DUPLIKASI ROW

        $query = MasterData::query()
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id');

        // 3. Tambahkan Data Kelistrikan via Subquery (Aman dari Group By Error)
        $query->addSelect([
            'master_data.*',

            // Subquery: ID Kelistrikan Terbaru
            'kelistrikan_id' => \App\Models\IGambarKelistrikan::select('id')
                ->whereColumn('master_data_id', 'master_data.id')
                ->latest()
                ->limit(1),

            // Subquery: Deskripsi Terbaru
            'kelistrikan_deskripsi' => \App\Models\IGambarKelistrikan::select('deskripsi')
                ->whereColumn('master_data_id', 'master_data.id')
                ->latest()
                ->limit(1),

            // Subquery: Jumlah Opsi Kelistrikan (PENTING untuk Indikator Multi-Opsi)
            'kelistrikan_count' => \App\Models\IGambarKelistrikan::selectRaw('count(*)')
                ->whereColumn('master_data_id', 'master_data.id'),

            // Subquery: ID File Fisik
            // Kita cari dari tabel file fisik langsung berdasarkan kombinasi ID
            'file_kelistrikan_id' => \App\Models\MasterKelistrikanFile::select('id')
                ->whereColumn('a_type_engine_id', 'master_data.a_type_engine_id')
                ->whereColumn('b_merk_id', 'master_data.b_merk_id')
                ->whereColumn('c_type_chassis_id', 'master_data.c_type_chassis_id')
                ->limit(1)
        ]);

        // 4. Eager Load Relasi Standar
        $query->with(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan']);

        // 5. Filter Search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('master_data.id', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.merek_dagang', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhereHas('gambarKelistrikan', function ($qKelistrikan) use ($search) {
                        $qKelistrikan->where('deskripsi', 'like', "%{$search}%");
                    });
            });
        }

        // 6. Sorting
        $sortColumn = match ($sortBy) {
            'id' => 'master_data.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'created_at' => 'master_data.created_at',
            'updated_at' => 'master_data.updated_at',
            'kelistrikan_deskripsi' => 'kelistrikan_deskripsi', // Sort by alias subquery
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
    public function trash(Request $request)
    {
        $search = $request->input('search', '');

        $query = MasterData::onlyTrashed()
            ->with(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan']);

        // Logika Pencarian di tabel relasi
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('typeEngine', fn($sub) => $sub->where('type_engine', 'like', "%{$search}%"))
                    ->orWhereHas('merk', fn($sub) => $sub->where('merk', 'like', "%{$search}%"))
                    ->orWhereHas('typeChassis', fn($sub) => $sub->where('type_chassis', 'like', "%{$search}%")->orWhere('merek_dagang', 'like', "%{$search}%"))
                    ->orWhereHas('jenisKendaraan', fn($sub) => $sub->where('jenis_kendaraan', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('deleted_at', 'desc')->get();
    }

    // --- FITUR BARU: Kosongkan Sampah ---
    public function emptyTrash()
    {
        $trashedItems = MasterData::onlyTrashed()->get();
        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($trashedItems as $item) {
            // Cek apakah Master Data ini dipakai di Varian Body (aktif atau terhapus)
            if (EVarianBody::where('master_data_id', $item->id)->withTrashed()->exists()) {
                $skippedCount++;
                continue; // Skip penghapusan
            }

            $item->forceDelete();
            $deletedCount++;
        }

        return response()->json([
            'message' => "Berhasil menghapus $deletedCount data. $skippedCount data dilewati karena masih terkait dengan Varian Body.",
            'deleted' => $deletedCount,
            'skipped' => $skippedCount
        ]);
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
