<?php

namespace App\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\UpdateTransaksiRequest;
use App\Http\Requests\StoreTransaksiRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use Illuminate\Http\Request;

class TransaksiController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        // 1. Validasi parameter yang dikirim dari Flutter
        $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100', // Batasi pilihan per halaman
            'sortBy' => 'nullable|string|in:id,created_at,updated_at', // Kolom yang boleh di-sort
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        // 2. Ambil parameter dengan nilai default
        $perPage = $request->input('perPage', 25);
        $sortBy = $request->input('sortBy', 'updated_at');
        $sortDirection = $request->input('sortDirection', 'desc');
        $search = $request->input('search', '');

        // 3. Query utama dengan eager loading yang sudah Anda definisikan
        $query = Transaksi::with([
            'user:id,name',
            'customer:id,nama_pt',
            'aTypeEngine',
            'bMerk',
            'cTypeChassis',
            'dJenisKendaraan',
            'fPengajuan'
        ]);

        // 4. Terapkan filter pencarian jika ada
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn($sub) => $sub->where('nama_pt', 'like', "%{$search}%"))
                    ->orWhereHas('aTypeEngine', fn($sub) => $sub->where('type_engine', 'like', "%{$search}%"))
                    ->orWhereHas('bMerk', fn($sub) => $sub->where('merk', 'like', "%{$search}%"))
                    ->orWhereHas('cTypeChassis', fn($sub) => $sub->where('type_chassis', 'like', "%{$search}%"))
                    ->orWhereHas('dJenisKendaraan', fn($sub) => $sub->where('jenis_kendaraan', 'like', "%{$search}%"))
                    ->orWhereHas('fPengajuan', fn($sub) => $sub->where('jenis_pengajuan', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn($sub) => $sub->where('name', 'like', "%{$search}%"));
            });
        }

        // 5. Terapkan sorting
        $query->orderBy($sortBy, $sortDirection);

        // 6. Lakukan paginasi dan kembalikan hasilnya
        $transaksis = $query->paginate($perPage);

        return response()->json($transaksis);
    }

    /**
     * Menyimpan transaksi baru.
     */
    public function store(StoreTransaksiRequest $request)
    {
        $validated = $request->validated();
        $jenisKendaraanId = $validated['d_jenis_kendaraan_id'];

        // --- Logika Membuat ID Unik (Sama seperti sebelumnya) ---
        $lastTransaksi = Transaksi::where('id', 'like', $jenisKendaraanId . '-%')->orderBy('id', 'desc')->first();
        $counter = 1;
        if ($lastTransaksi) {
            $parts = explode('-', $lastTransaksi->id);
            $counter = intval(end($parts)) + 1;
        }
        $newId = $jenisKendaraanId . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);

        // --- Logika Baru: Ekstrak ID Induk ---
        $engineId = substr($jenisKendaraanId, 0, 2);
        $merkId = substr($jenisKendaraanId, 0, 4);
        $chassisId = substr($jenisKendaraanId, 0, 7);

        $transaksi = Transaksi::create([
            'id' => $newId,
            'a_type_engine_id' => $engineId,       // <-- simpan data baru
            'b_merk_id' => $merkId,             // <-- simpan data baru
            'c_type_chassis_id' => $chassisId,        // <-- simpan data baru
            'd_jenis_kendaraan_id' => $jenisKendaraanId,
            'customer_id' => $validated['customer_id'],
            'f_pengajuan_id' => $validated['f_pengajuan_id'],
            'user_id' => Auth::id(),
        ]);

        return response()->json($transaksi->load(['user', 'customer', 'aTypeEngine', 'bMerk', 'cTypeChassis', 'dJenisKendaraan', 'fPengajuan']), 201);
    }

    /**
     * Menampilkan satu data transaksi spesifik.
     */
    public function show(Transaksi $transaksi)
    {
        return response()->json($transaksi->load(['user', 'customer', 'aTypeEngine', 'bMerk', 'cTypeChassis', 'dJenisKendaraan', 'fPengajuan']));
    }

    /**
     * Memperbarui data transaksi.
     */
    public function update(UpdateTransaksiRequest $request, Transaksi $transaksi)
    {
        $this->authorize('update', $transaksi);
        $transaksi->update($request->validated());
        return response()->json($transaksi->fresh()->load(['user', 'customer', 'aTypeEngine', 'bMerk', 'cTypeChassis', 'dJenisKendaraan', 'fPengajuan']));
    }

    /**
     * Menghapus data transaksi.
     */
    public function destroy(Transaksi $transaksi)
    {
        $this->authorize('delete', $transaksi);
        $transaksi->delete();
        return response()->json(null, 204);
    }
}
