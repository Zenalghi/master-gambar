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
}
