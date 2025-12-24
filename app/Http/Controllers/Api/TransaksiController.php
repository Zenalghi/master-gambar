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
     * Menampilkan data transaksi dengan paginasi, filter, dan sort.
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
            'customer' => 'nullable|string',
            'type_engine' => 'nullable|string',
            'merk' => 'nullable|string',
            'type_chassis' => 'nullable|string',
            'jenis_kendaraan' => 'nullable|string',
            'jenis_pengajuan' => 'nullable|string',
            'user' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 50;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // 2. Query Utama
        $query = Transaksi::query()
            ->join('customers', 'z_transaksi.customer_id', '=', 'customers.id')
            ->join('f_pengajuan', 'z_transaksi.f_pengajuan_id', '=', 'f_pengajuan.id')
            ->join('users', 'z_transaksi.user_id', '=', 'users.id')
            ->join('master_data', 'z_transaksi.master_data_id', '=', 'master_data.id')
            // ... (Join Master Data Components Tetap Sama) ...
            ->join('a_type_engines', 'master_data.a_type_engine_id', '=', 'a_type_engines.id')
            ->join('b_merks', 'master_data.b_merk_id', '=', 'b_merks.id')
            ->join('c_type_chassis', 'master_data.c_type_chassis_id', '=', 'c_type_chassis.id')
            ->join('d_jenis_kendaraan', 'master_data.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')

            // --- [1] LEFT JOIN KE DETAILS UNTUK AMBIL TANGGAL ---
            ->leftJoin('z_transaksi_details', 'z_transaksi.id', '=', 'z_transaksi_details.transaksi_id')

            // --- [2] SELECT DENGAN LOGIKA TANGGAL TERBARU ---
            ->select([
                'z_transaksi.*',
                // Logika: Ambil tanggal terbesar antara Transaksi Utama vs Detail.
                // Jika Detail null (belum pernah save/draft), pakai Transaksi Utama.
                \Illuminate\Support\Facades\DB::raw('
                    GREATEST(
                        z_transaksi.updated_at, 
                        COALESCE(z_transaksi_details.updated_at, z_transaksi.updated_at)
                    ) as latest_activity_at
                ')
            ]);

        // 3. Eager Load (Tetap Sama)
        $query->with([
            'user:id,name',
            'customer:id,nama_pt',
            'fPengajuan',
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan',
            'detail'
        ]);

        // 4. Filter Map (Tetap Sama)
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

        // 5. Global Search (Tetap Sama)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('z_transaksi.id', 'like', "%{$search}%")
                    ->orWhere('customers.nama_pt', 'like', "%{$search}%")
                    ->orWhere('a_type_engines.type_engine', 'like', "%{$search}%")
                    ->orWhere('b_merks.merk', 'like', "%{$search}%")
                    ->orWhere('c_type_chassis.type_chassis', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('f_pengajuan.jenis_pengajuan', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%")
                    ->orWhere('z_transaksi.created_at', 'like', "%{$search}%")
                    ->orWhere('z_transaksi.updated_at', 'like', "%{$search}%")
                    ->orWhere('z_transaksi_details.updated_at', 'like', "%{$search}%");
            });
        }

        // 6. Sorting
        $sortColumn = match ($sortBy) {
            'id' => 'z_transaksi.id',
            'customer' => 'customers.nama_pt',
            'type_engine' => 'a_type_engines.type_engine',
            'merk' => 'b_merks.merk',
            'type_chassis' => 'c_type_chassis.type_chassis',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'jenis_pengajuan' => 'f_pengajuan.jenis_pengajuan',
            'user' => 'users.name',
            'created_at' => 'z_transaksi.created_at',
            'updated_at' => 'latest_activity_at',

            default => 'latest_activity_at', // Default sort juga pakai tanggal aktivitas terbaru
        };
        $query->orderBy($sortColumn, $sortDirection);

        // 7. Pagination & Transformasi
        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function ($item) {
            $item->a_type_engine = $item->masterData->typeEngine ?? null;
            $item->b_merk = $item->masterData->merk ?? null;
            $item->c_type_chassis = $item->masterData->typeChassis ?? null;
            $item->d_jenis_kendaraan = $item->masterData->jenisKendaraan ?? null;
            $item->judul_gambar_string = $item->judul_gambar_string;
            if (isset($item->latest_activity_at)) {
                $item->updated_at = $item->latest_activity_at;
            }

            return $item;
        });

        return $paginator;
    }
    /**
     * Menyimpan transaksi baru.
     */
    public function store(StoreTransaksiRequest $request)
    {
        $validated = $request->validated();

        $transaksi = Transaksi::create([
            'master_data_id' => $validated['master_data_id'],
            'customer_id'    => $validated['customer_id'],
            'f_pengajuan_id' => $validated['f_pengajuan_id'],
            'user_id'        => Auth::id(),
        ]);

        // Load relasi untuk respon JSON
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

    public function update(UpdateTransaksiRequest $request, Transaksi $transaksi)
    {
        $this->authorize('update', $transaksi);
        $validated = $request->validated();

        // FIX: Update langsung menggunakan ID yang dikirim
        $transaksi->update([
            'master_data_id' => $validated['master_data_id'],
            'customer_id'    => $validated['customer_id'],
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

    public function destroy(Transaksi $transaksi)
    {
        $this->authorize('delete', $transaksi);

        // 1. Hapus detail transaksi terlebih dahulu
        // Asumsi relasi di model Transaksi bernama 'detail' atau 'transaksiDetail'
        if ($transaksi->detail) {
            $transaksi->detail()->delete();
        }

        // Jika relasinya hasMany (banyak detail), gunakan:
        // $transaksi->details()->delete();

        // 2. Baru hapus transaksinya
        $transaksi->delete();

        return response()->noContent();
    }
}
