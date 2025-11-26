<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ATypeEngine;
use App\Models\BMerk;
use App\Models\CTypeChassis;
use App\Models\Customer;
use App\Models\DJenisKendaraan;
use App\Models\EVarianBody;
use App\Models\FPengajuan;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\GGambarUtama;
use App\Models\HGambarOptional;
use App\Models\IGambarKelistrikan;
use App\Models\JJudulGambar;
use App\Models\MasterData;

class _OptionController extends Controller
{
    // === DATA DROPDOWN KENDARAAN ===

    public function getOptionsTypeEngine(Request $request)
    {
        $search = $request->input('search', '');
        return ATypeEngine::query()
            ->where('type_engine', 'like', "%{$search}%")
            ->whereNull('deleted_at') // Hanya ambil yang tidak di-soft-delete
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get(['id', 'type_engine as name']); // Format 'name' agar seragam
    }

    /**
     * Mengambil daftar Merk (searchable, limit 30).
     */
    public function getOptionsMerk(Request $request)
    {
        $search = $request->input('search', '');
        return BMerk::query()
            ->where('merk', 'like', "%{$search}%")
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get(['id', 'merk as name']);
    }

    /**
     * Mengambil daftar Type Chassis (searchable, limit 30).
     */
    public function getOptionsTypeChassis(Request $request)
    {
        $search = $request->input('search', '');
        return CTypeChassis::query()
            ->where('type_chassis', 'like', "%{$search}%")
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get(['id', 'type_chassis as name']);
    }

    /**
     * Mengambil daftar Jenis Kendaraan (searchable, limit 30).
     */
    public function getOptionsJenisKendaraan(Request $request)
    {
        $search = $request->input('search', '');
        return DJenisKendaraan::query()
            ->where('jenis_kendaraan', 'like', "%{$search}%")
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get(['id', 'jenis_kendaraan as name']);
    }

    // === 2. DROPDOWN CERDAS (UNTUK FORM VARIAN BODY) ===

    /**
     * Mengambil daftar MasterData yang sudah jadi (searchable, limit 30).
     */
    public function getOptionsMasterData(Request $request)
    {
        $search = $request->input('search', '');

        $query = MasterData::query()
            ->with(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan'])
            ->where(function ($q) use ($search) {
                // Cari di semua kolom relasi
                $q->whereHas('typeEngine', fn($sub) => $sub->where('type_engine', 'like', "%{$search}%"))
                    ->orWhereHas('merk', fn($sub) => $sub->where('merk', 'like', "%{$search}%"))
                    ->orWhereHas('typeChassis', fn($sub) => $sub->where('type_chassis', 'like', "%{$search}%"))
                    ->orWhereHas('jenisKendaraan', fn($sub) => $sub->where('jenis_kendaraan', 'like', "%{$search}%"));
            })
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->limit(30);

        // Ubah format data agar mudah dibaca di dropdown Flutter
        $results = $query->get()->map(function ($item) {
            // Cek untuk jaga-jaga jika ada relasi yang terhapus
            $engine = $item->typeEngine->type_engine ?? 'N/A';
            $merk = $item->merk->merk ?? 'N/A';
            $chassis = $item->typeChassis->type_chassis ?? 'N/A';
            $jenis = $item->jenisKendaraan->jenis_kendaraan ?? 'N/A';

            return [
                'id' => $item->id,
                'name' => "$engine / $merk / $chassis / $jenis"
            ];
        });

        return response()->json($results);
    }
    // Ganti parameternya menjadi $master_data_id
    public function getVarianBody($master_data_id)
    {
        // Cari berdasarkan master_data_id
        return response()->json(
            EVarianBody::where('master_data_id', $master_data_id)
                ->select('id', 'varian_body')
                ->get()
        );
    }
    // === DATA DROPDOWN FORM UTAMA ===

    public function getUsers()
    {
        // Hanya ambil user dengan role drafter untuk dropdown 'Digambar'
        return response()->json(User::where('role', 'drafter')->select('id', 'name', 'signature')->get());
    }

    public function getCustomers(Request $request)
    {
        $search = $request->input('search', '');

        return response()->json(
            Customer::query()
                ->where('nama_pt', 'like', "%{$search}%") // Filter berdasarkan nama
                ->orderBy('nama_pt', 'asc')
                ->limit(20)
                ->select('id', 'nama_pt', 'pj', 'signature_pj')
                ->get()
        );
    }
    public function getPengajuan()
    {
        return response()->json(FPengajuan::all());
    }
    public function getRoles()
    {
        return response()->json(Role::all());
    }

    public function getPemeriksa()
    {
        // Asumsi: 'pemeriksa' adalah nama role di tabel roles Anda.
        // Sesuaikan 'pemeriksa' jika nama rolenya berbeda.
        return response()->json(User::whereHas('role', function ($query) {
            $query->where('name', 'pemeriksa');
        })->select('id', 'name')->get());
    }

    // Jangan lupa 'use App\Models\HGambarOptional;' di bagian atas file
    public function getGambarOptional()
    {
        // Ambil id dan deskripsi untuk ditampilkan di dropdown
        return response()->json(HGambarOptional::select('id', 'deskripsi')->get());
    }

    // Jangan lupa 'use App\Models\IGambarKelistrikan;' di bagian atas file
    public function getGambarKelistrikan($chassis_id)
    {
        return response()->json(
            IGambarKelistrikan::where('c_type_chassis_id', $chassis_id)->select('id', 'deskripsi')->get()
        );
    }
    public function getJudulGambar()
    {
        return response()->json(JJudulGambar::select('id', 'nama_judul as name')->get());
    }

    public function getGambarOptionalByVarian(Request $request)
    {
        // 1. Validasi input untuk memastikan kita menerima array
        $validated = $request->validate([
            'varian_ids' => 'required|array',
            'varian_ids.*' => 'integer|exists:e_varian_body,id',
        ]);

        // 2. Ambil data Gambar Optional di mana 'e_varian_body_id'
        //    ada di dalam array 'varian_ids' yang dikirim dari Flutter.
        $gambarOptions = HGambarOptional::whereIn('e_varian_body_id', $validated['varian_ids'])
            ->select('id', 'deskripsi')
            ->where('tipe', 'independen')
            ->get();

        return response()->json($gambarOptions);
    }

    public function getDependentOptionals(Request $request)
    {
        $validated = $request->validate([
            'varian_ids' => 'required|array',
            'varian_ids.*' => 'integer|exists:e_varian_body,id',
        ]);

        // Cari ID Gambar Utama yang terkait dengan Varian Body yang dipilih
        $gambarUtamaIds = GGambarUtama::whereIn('e_varian_body_id', $validated['varian_ids'])
            ->pluck('id');

        // Jika tidak ada, kembalikan array kosong
        if ($gambarUtamaIds->isEmpty()) {
            return response()->json([]);
        }

        // Ambil semua Gambar Optional paket yang terkait dengan Gambar Utama tersebut
        $dependentOptionals = HGambarOptional::whereIn('g_gambar_utama_id', $gambarUtamaIds)
            ->where('tipe', 'paket')
            ->select('id', 'deskripsi')
            ->get();

        return response()->json($dependentOptionals);
    }

    public function checkPaketOptionalExists($varianBodyId)
    {
        $varianBody = EVarianBody::find($varianBodyId);
        if (!$varianBody) {
            return response()->json(['exists' => false]); // Atau error 404
        }

        // Cek apakah ada Gambar Utama yang terhubung ke Varian Body ini,
        // DAN Gambar Utama tersebut memiliki Gambar Optional bertipe 'paket'
        $exists = $varianBody->gambarUtama()
            ->whereHas('gambarOptionals', function ($query) {
                $query->where('tipe', 'paket');
            })
            ->exists();

        return response()->json(['exists' => $exists]);
    }
}
