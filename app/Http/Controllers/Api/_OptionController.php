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
use App\Models\HGambarOptional;
use App\Models\IGambarKelistrikan;
use App\Models\JJudulGambar;

class _OptionController extends Controller
{
    // === DATA DROPDOWN KENDARAAN ===

    public function getTypeEngines()
    {
        return response()->json(ATypeEngine::all());
    }

    public function getMerks($engine_id)
    {
        return response()->json(BMerk::where('id', 'like', $engine_id . '%')->get());
    }

    public function getTypeChassis($merk_id)
    {
        return response()->json(CTypeChassis::where('id', 'like', $merk_id . '%')->get());
    }

    public function getJenisKendaraan($chassis_id)
    {
        return response()->json(DJenisKendaraan::where('id', 'like', $chassis_id . '%')->get());
    }

    public function getVarianBody($jenis_kendaraan_id)
    {
        return response()->json(EVarianBody::where('jenis_kendaraan_id', $jenis_kendaraan_id)->get());
    }

    // public function getPengajuan($varian_body_id)
    // {
    //     return response()->json(FPengajuan::where('varian_body_id', $varian_body_id)->get());
    // }

    // === DATA DROPDOWN FORM UTAMA ===

    public function getUsers()
    {
        // Hanya ambil user dengan role drafter untuk dropdown 'Digambar'
        return response()->json(User::where('role', 'drafter')->select('id', 'name', 'signature')->get());
    }

    public function getCustomers()
    {
        return response()->json(Customer::select('id', 'nama_pt', 'pj', 'signature_pj')->get());
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
            ->select('id', 'deskripsi') // Hanya ambil kolom yang dibutuhkan untuk dropdown
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
        $gambarUtamaIds = \App\Models\GGambarUtama::whereIn('e_varian_body_id', $validated['varian_ids'])
            ->pluck('id');

        // Jika tidak ada, kembalikan array kosong
        if ($gambarUtamaIds->isEmpty()) {
            return response()->json([]);
        }

        // Ambil semua Gambar Optional Dependen yang terkait dengan Gambar Utama tersebut
        $dependentOptionals = \App\Models\HGambarOptional::whereIn('g_gambar_utama_id', $gambarUtamaIds)
            ->where('tipe', 'dependen')
            ->select('id', 'deskripsi')
            ->get();

        return response()->json($dependentOptionals);
    }
}
