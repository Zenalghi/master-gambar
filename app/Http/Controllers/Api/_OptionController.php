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
        // 1. Ambil semua data (select 'nama_judul as name' agar sesuai format Flutter)
        $data = JJudulGambar::select('id', 'nama_judul as name')->get();

        // 2. Lakukan Sorting Natural menggunakan Collection Laravel
        // SORT_NATURAL akan menganggap angka dalam string sebagai angka, bukan teks.
        // values() penting agar hasil JSON kembali menjadi array index [0,1,2..] (bukan object dengan key ID)
        $sortedData = $data->sortBy('name', SORT_NATURAL)->values();

        return response()->json($sortedData);
    }

    public function getGambarOptionalByVarian(Request $request)
    {
        $validated = $request->validate([
            'varian_ids' => 'required|array',
            'varian_ids.*' => 'integer|exists:e_varian_body,id',
        ]);

        // 1. Ambil semua Master Data ID unik dari Varian Body yang dikirim
        $varianBodies = EVarianBody::whereIn('id', $validated['varian_ids'])
            ->select('id', 'master_data_id')
            ->get();

        $masterDataIds = $varianBodies->pluck('master_data_id')->unique();

        // 2. Ambil Gambar Optional berdasarkan MASTER DATA ID
        $gambarOptions = HGambarOptional::whereIn('master_data_id', $masterDataIds)
            ->select('id', 'deskripsi', 'master_data_id') // Select master_data_id untuk sorting
            ->where('tipe', 'independen')
            ->get();

        // 3. Logic Sorting (Sedikit lebih tricky karena mappingnya Varian -> Master -> Gambar)
        // Kita ingin urutan output tetap sesuai urutan input varian_ids di frontend

        $urutanVarianInput = array_flip($validated['varian_ids']); // [VarianA => 0, VarianB => 1]

        // Buat map: MasterDataID => UrutanTerkechil (Prioritas)
        $masterDataPriority = [];
        foreach ($varianBodies as $vb) {
            $urutan = $urutanVarianInput[$vb->id] ?? 999;
            // Jika MasterData ini sudah punya urutan, ambil yang lebih kecil (muncul duluan)
            if (!isset($masterDataPriority[$vb->master_data_id]) || $urutan < $masterDataPriority[$vb->master_data_id]) {
                $masterDataPriority[$vb->master_data_id] = $urutan;
            }
        }

        $sortedOptions = $gambarOptions->sortBy(function ($item) use ($masterDataPriority) {
            // Sort berdasarkan kapan Master Data pemilik gambar ini muncul di list input user
            $index = $masterDataPriority[$item->master_data_id] ?? 999;
            return [$index, $item->id];
        })->values();

        return response()->json($sortedOptions);
    }

    public function getDependentOptionals(Request $request)
    {
        // 1. Validasi input: Kita butuh varian_ids DAN judul_ids
        $validated = $request->validate([
            'varian_ids' => 'required|array',
            'varian_ids.*' => 'integer|exists:e_varian_body,id',
            'judul_ids' => 'nullable|array',
            'judul_ids.*' => 'nullable|integer|exists:j_judul_gambars,id',
        ]);

        $orderedOptionals = collect();
        $judulIds = $validated['judul_ids'] ?? [];

        // Loop berdasarkan index agar Varian dan Judul sinkron
        foreach ($validated['varian_ids'] as $index => $varianId) {

            // Ambil Judul Gambar pasangannya (jika ada)
            $judulId = $judulIds[$index] ?? null;
            $namaJudulSuffix = '';

            if ($judulId) {
                $judulModel = \App\Models\JJudulGambar::find($judulId);
                if ($judulModel) {
                    $namaJudulSuffix = ' ' . $judulModel->nama_judul;
                }
            }

            // 1. Cari Gambar Utama untuk varian ini
            $gambarUtama = GGambarUtama::where('e_varian_body_id', $varianId)->first();

            if ($gambarUtama) {
                // 2. Ambil Optional Paket milik Gambar Utama ini
                $optionals = HGambarOptional::where('g_gambar_utama_id', $gambarUtama->id)
                    ->where('tipe', 'paket')
                    ->select('id', 'deskripsi')
                    ->get();

                // 3. Masukkan ke koleksi hasil & Modifikasi Deskripsi
                foreach ($optionals as $opt) {
                    // GABUNGKAN DESKRIPSI + NAMA JUDUL
                    $opt->deskripsi = $opt->deskripsi . $namaJudulSuffix;
                    $orderedOptionals->push($opt);
                }
            }
        }

        return response()->json($orderedOptionals);
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

    public function getVarianBodyForDropdown(Request $request)
    {
        $search = $request->input('search', '');

        $query = \App\Models\EVarianBody::query()
            ->select('id', 'varian_body')
            // Load relasi gambarUtama untuk cek kolom spesifik
            ->with(['gambarUtama:e_varian_body_id,path_gambar_utama,path_gambar_terurai,path_gambar_kontruksi'])
            ->where('varian_body', 'like', "%{$search}%")
            ->limit(30);

        if ($request->has('master_data_id') && !empty($request->master_data_id)) {
            $query->where('master_data_id', $request->master_data_id);
        }

        $results = $query->get()->map(function ($item) {
            $gbr = $item->gambarUtama; // Relasi HasOne

            return [
                'id' => $item->id,
                'name' => $item->varian_body,

                // Flag status ketersediaan file
                // Cek apakah relasi ada DAN path tidak null/kosong
                'has_gambar' => $gbr && !empty($gbr->path_gambar_utama),
                'has_terurai' => $gbr && !empty($gbr->path_gambar_terurai),
                'has_kontruksi' => $gbr && !empty($gbr->path_gambar_kontruksi),
            ];
        });

        return response()->json($results);
    }
    /**
     * Mengecek status kelistrikan berdasarkan Master Data ID.
     * Mengembalikan status apakah File hilang, Deskripsi hilang, atau Lengkap.
     */
    public function getKelistrikanStatusByMasterData($masterDataId)
    {
        // 1. Ambil Master Data
        $masterData = \App\Models\MasterData::find($masterDataId);

        if (!$masterData) {
            return response()->json([
                'status_code' => 'error',
                'display_text' => 'Error: Master Data Invalid'
            ]);
        }

        // 2. Cek File Fisik (1 File per Chassis)
        $fileFisik = \App\Models\MasterKelistrikanFile::where('a_type_engine_id', $masterData->a_type_engine_id)
            ->where('b_merk_id', $masterData->b_merk_id)
            ->where('c_type_chassis_id', $masterData->c_type_chassis_id)
            ->first();

        // 3. Cek Deskripsi Logis (Bisa BANYAK) - Gunakan get()
        $descLogisList = \App\Models\IGambarKelistrikan::where('master_data_id', $masterDataId)
            ->orderBy('id', 'desc')
            ->get();

        // 4. Struktur Respon Default
        $response = [
            'file_id' => $fileFisik ? $fileFisik->id : null,
            'status_code' => 'ok',
            'display_text' => '',
            'options' => [], // Wadah list opsi
            'selected_id' => null // Wadah auto-select
        ];

        // 5. Logika Status
        if (!$fileFisik) {
            $response['status_code'] = 'missing_file';
            $response['display_text'] = 'File gambar kelistrikan belum ditambahkan';
        } elseif ($descLogisList->isEmpty()) {
            $response['status_code'] = 'missing_desc';
            $response['display_text'] = 'Deskripsi kelistrikan belum ditambahkan';
        } else {
            // Jika Data Lengkap
            if ($descLogisList->count() == 1) {
                // Kasus: Single Option
                $item = $descLogisList->first();
                $response['status_code'] = 'ready';
                $response['display_text'] = $item->deskripsi;
                $response['selected_id'] = $item->id;

                // Tetap kirim options array agar dialog edit di Master Data Screen bisa membacanya
                $response['options'] = [[
                    'id' => $item->id,
                    'deskripsi' => $item->deskripsi
                ]];
            } else {
                // Kasus: Multiple Options
                $response['status_code'] = 'multiple_options';
                $response['display_text'] = 'Pilih Opsi Kelistrikan';

                // Map ke array sederhana
                $response['options'] = $descLogisList->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'deskripsi' => $item->deskripsi
                    ];
                })->values();
            }
        }

        return response()->json($response);
    }
    // Ambil Gambar Optional Independen by Master Data ID
    public function getIndependentOptions($masterDataId)
    {
        $data = HGambarOptional::where('master_data_id', $masterDataId)
            ->where('tipe', 'independen')
            ->select('id', 'deskripsi')
            ->orderBy('id', 'asc') // Urutan default (sebelum di-reorder user)
            ->get();

        // Format agar sesuai Dropdown/OptionItem Flutter
        $formatted = $data->map(function ($item) {
            return ['id' => $item->id, 'name' => $item->deskripsi];
        });

        return response()->json($formatted);
    }
}
