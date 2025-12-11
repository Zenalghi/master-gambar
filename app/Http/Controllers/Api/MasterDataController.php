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
            // Tambahkan kelistrikan_deskripsi ke sorting
            'sortBy' => 'nullable|string',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 50;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query utama
        $query = \App\Models\MasterData::query()
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')

            // --- JOIN 1: FILE FISIK (Match 3 ID: Engine, Merk, Chassis) ---
            // Gunakan Left Join agar Master Data tetap muncul meski file belum ada
            ->leftJoin('master_kelistrikan_files', function ($join) {
                $join->on('master_data.a_type_engine_id', '=', 'master_kelistrikan_files.a_type_engine_id')
                    ->on('master_data.b_merk_id', '=', 'master_kelistrikan_files.b_merk_id')
                    ->on('master_data.c_type_chassis_id', '=', 'master_kelistrikan_files.c_type_chassis_id');
            })

            // --- JOIN 2: DESKRIPSI (Match Master Data ID) ---
            ->leftJoin('i_gambar_kelistrikan', 'master_data.id', '=', 'i_gambar_kelistrikan.master_data_id')

            ->select([
                'master_data.*',
                'i_gambar_kelistrikan.id as kelistrikan_id',
                'i_gambar_kelistrikan.deskripsi as kelistrikan_deskripsi',
                \Illuminate\Support\Facades\DB::raw('COALESCE(i_gambar_kelistrikan.master_kelistrikan_file_id, master_kelistrikan_files.id) as file_kelistrikan_id'),
            ])
            ->groupBy('master_data.id');

        // 3. Eager load relasi (untuk JSON bersarang di Flutter)
        $query->with(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan']);

        // 4. Filter Search (Gunakan nama tabel eksplisit!)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('master_data.id', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('i_gambar_kelistrikan.deskripsi', 'like', "%{$search}%");
            });
        }

        // 5. Sorting (Mapping ke kolom tabel yang benar)
        $sortColumn = match ($sortBy) {
            'id' => 'master_data.id',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'created_at' => 'master_data.created_at',
            'updated_at' => 'master_data.updated_at',
            'kelistrikan_deskripsi' => 'i_gambar_kelistrikan.deskripsi',
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
