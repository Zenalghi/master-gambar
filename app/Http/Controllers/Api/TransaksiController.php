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
        $transaksis = Transaksi::with(['user:id,name', 'customer:id,nama_pt', 'dJenisKendaraan', 'fPengajuan'])->latest()->get();
        return response()->json($transaksis);
    }

    /**
     * Menyimpan transaksi baru.
     */
    public function store(StoreTransaksiRequest $request)
    {
        $validated = $request->validated();

        $baseId = $validated['d_jenis_kendaraan_id'];
        $lastTransaksi = Transaksi::where('id', 'like', $baseId . '-%')->orderBy('id', 'desc')->first();

        $counter = 1;
        if ($lastTransaksi) {
            $parts = explode('-', $lastTransaksi->id);
            $counter = intval(end($parts)) + 1;
        }
        $newId = $baseId . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);

        $transaksi = Transaksi::create([
            'id' => $newId,
            'customer_id' => $validated['customer_id'],
            'd_jenis_kendaraan_id' => $validated['d_jenis_kendaraan_id'],
            'f_pengajuan_id' => $validated['f_pengajuan_id'],
            'user_id' => Auth::id(),
        ]);

        return response()->json($transaksi->load(['user', 'customer', 'dJenisKendaraan', 'fPengajuan']), 201);
    }

    /**
     * Menampilkan satu data transaksi spesifik.
     */
    public function show(Transaksi $transaksi)
    {
        return response()->json($transaksi->load(['user', 'customer', 'dJenisKendaraan', 'fPengajuan']));
    }

    /**
     * Memperbarui data transaksi.
     */
    public function update(UpdateTransaksiRequest $request, Transaksi $transaksi)
    {
        $this->authorize('update', $transaksi);
        $transaksi->update($request->validated());
        return response()->json($transaksi->fresh()->load(['user', 'customer', 'dJenisKendaraan', 'fPengajuan']));
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
