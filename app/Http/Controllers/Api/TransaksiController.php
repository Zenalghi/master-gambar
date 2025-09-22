<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransaksiRequest;
use App\Http\Requests\UpdateTransaksiRequest;
use App\Models\Transaksi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // <-- Pastikan ini ada

class TransaksiController extends Controller
{
    use AuthorizesRequests; // <-- Dan ini juga ada

    /**
     * Menampilkan semua data transaksi.
     */
    public function index()
    {
        $transaksis = Transaksi::with([
            'user:id,name',
            'customer:id,nama_pt',
            'aTypeEngine', // <-- eager load
            'bMerk',       // <-- eager load
            'cTypeChassis', // <-- eager load
            'dJenisKendaraan',
            'fPengajuan'
        ])->latest()->get();

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
