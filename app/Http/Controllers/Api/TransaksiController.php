<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransaksiRequest;
use App\Http\Requests\UpdateTransaksiRequest;
use App\Models\MasterData;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TransaksiController extends Controller
{
    use AuthorizesRequests;

    /**
     * Menampilkan data transaksi dengan paginasi, filter, dan sort
     * yang sesuai dengan arsitektur MasterData.
     */
    public function index(Request $request)
    {
        // 1. Validasi parameter
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string|in:id,customer,type_engine,merk,type_chassis,jenis_kendaraan,jenis_pengajuan,user,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
            // Filter lanjutan (berbasis teks)
            'customer' => 'nullable|string',
            'type_engine' => 'nullable|string',
            'merk' => 'nullable|string',
            'type_chassis' => 'nullable|string',
            'jenis_kendaraan' => 'nullable|string',
            'jenis_pengajuan' => 'nullable|string',
            'user' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query utama
        $query = Transaksi::query()
            // JOIN ke tabel relasi untuk sorting dan advanced filter
            ->join('customers', 'transaksis.customer_id', '=', 'customers.id')
            ->join('f_pengajuan', 'transaksis.f_pengajuan_id', '=', 'f_pengajuan.id')
            ->join('users', 'transaksis.user_id', '=', 'users.id')
            ->join('master_data', 'transaksis.master_data_id', '=', 'master_data.id')
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->select('transaksis.*'); // <-- Penting!

        // 3. Eager load relasi (untuk struktur JSON)
        $query->with([
            'user:id,name',
            'customer:id,nama_pt',
            'fPengajuan',
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan'
        ]);

        // 4. Terapkan Advanced Filter (berbasis teks)
        $filterMap = [
            'customer' => 'customers.nama_pt',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'jenis_pengajuan' => 'f_pengajuan.jenis_pengajuan',
            'user' => 'users.name',
        ];

        foreach ($filterMap as $key => $column) {
            if ($request->filled($key)) {
                $query->where($column, 'like', '%' . $request->input($key) . '%');
            }
        }

        // 5. Terapkan Global Search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('transaksis.id', 'like', "%{$search}%")
                    ->orWhere('customers.nama_pt', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('f_pengajuan.jenis_pengajuan', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%");
            });
        }

        // 6. Terapkan Sorting
        $sortColumn = match ($sortBy) {
            'id' => 'transaksis.id',
            'customer' => 'customers.nama_pt',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'jenis_pengajuan' => 'f_pengajuan.jenis_pengajuan',
            'user' => 'users.name',
            'created_at' => 'transaksis.created_at',
            'updated_at' => 'transaksis.updated_at',
            default => 'transaksis.updated_at',
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 7. Lakukan paginasi
        return $query->paginate($perPage);
    }

    /**
     * Menyimpan transaksi baru.
     */
    public function store(StoreTransaksiRequest $request)
    {
        $validated = $request->validated();

        // 1. Temukan (atau buat baru) MasterData berdasarkan 4 ID
        $masterData = MasterData::firstOrCreate(
            [
                'a_type_engine_id' => $validated['a_type_engine_id'],
                'b_merk_id' => $validated['b_merk_id'],
                'c_type_chassis_id' => $validated['c_type_chassis_id'],
                'd_jenis_kendaraan_id' => $validated['d_jenis_kendaraan_id'],
            ]
        );

        // 2. Buat Transaksi baru
        $transaksi = Transaksi::create([
            'master_data_id' => $masterData->id,
            'customer_id' => $validated['customer_id'],
            'f_pengajuan_id' => $validated['f_pengajuan_id'],
            'user_id' => Auth::id(),
        ]);
        // ID (mmyy-xxxx) akan dibuat secara otomatis oleh Model Transaksi

        // 3. Muat relasi baru untuk respons
        $transaksi->load([
            'user',
            'customer',
            'fPengajuan',
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan'
        ]);

        return response()->json($transaksi, 201);
    }

    /**
     * Menampilkan satu data transaksi spesifik.
     */
    public function show(Transaksi $transaksi)
    {
        $transaksi->load([
            'user',
            'customer',
            'fPengajuan',
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan'
        ]);
        return response()->json($transaksi);
    }

    /**
     * Memperbarui data transaksi.
     */
    public function update(UpdateTransaksiRequest $request, Transaksi $transaksi)
    {
        $this->authorize('update', $transaksi);
        $validated = $request->validated();

        // Temukan (atau buat) MasterData baru jika ada perubahan
        $masterData = MasterData::firstOrCreate([
            'a_type_engine_id' => $validated['a_type_engine_id'],
            'b_merk_id' => $validated['b_merk_id'],
            'c_type_chassis_id' => $validated['c_type_chassis_id'],
            'd_jenis_kendaraan_id' => $validated['d_jenis_kendaraan_id'],
        ]);

        $transaksi->update([
            'master_data_id' => $masterData->id,
            'customer_id' => $validated['customer_id'],
            'f_pengajuan_id' => $validated['f_pengajuan_id'],
        ]);

        $transaksi->fresh()->load([
            'user',
            'customer',
            'fPengajuan',
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan'
        ]);

        return response()->json($transaksi);
    }

    /**
     * Menghapus (Soft Delete) data transaksi.
     */
    public function destroy(Transaksi $transaksi)
    {
        $this->authorize('delete', $transaksi);
        $transaksi->delete(); // Asumsi Transaksi juga pakai SoftDeletes
        return response()->noContent();
    }
}
