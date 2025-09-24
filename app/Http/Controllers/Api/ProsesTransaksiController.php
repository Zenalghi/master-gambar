<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\TransaksiVarian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProsesTransaksiController extends Controller
{
    public function simpanDetail(Request $request, Transaksi $transaksi)
    {
        $validated = $request->validate([
            'pemeriksa_id' => 'required|exists:users,id',
            'varian_body_ids' => 'required|array|min:1',
            'varian_body_ids.*' => 'required|exists:e_varian_body,id',
            // Tambahkan validasi untuk optional & kelistrikan jika perlu
        ]);

        try {
            DB::beginTransaction();

            // 1. Simpan detail utama (pemeriksa)
            $detail = TransaksiDetail::updateOrCreate(
                ['z_transaksi_id' => $transaksi->id],
                ['pemeriksa_id' => $validated['pemeriksa_id']]
            );

            // 2. Hapus varian lama (jika ada) untuk diganti dengan yang baru
            $detail->varians()->delete();

            // 3. Simpan daftar varian body yang baru dipilih
            foreach ($validated['varian_body_ids'] as $index => $varian_id) {
                TransaksiVarian::create([
                    'z_transaksi_detail_id' => $detail->id,
                    'e_varian_body_id' => $varian_id,
                    'urutan' => $index + 1, // Urutan mulai dari 1
                ]);
            }

            DB::commit();

            // --- LOGIKA PEMBUATAN PDF LENGKAP AKAN DITARUH DI SINI DI LANGKAH BERIKUTNYA ---

            return response()->json([
                'message' => 'Detail transaksi berhasil disimpan.',
                'data' => $detail->load('varians') // Kirim kembali data yang tersimpan
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan saat menyimpan data.', 'error' => $e->getMessage()], 500);
        }
    }
}
