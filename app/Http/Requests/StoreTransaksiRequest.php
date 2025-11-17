<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransaksiRequest extends FormRequest
{
    /**
     * Tentukan apakah user diizinkan membuat request ini.
     * Karena semua user yang login bisa membuat, kita set ke true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Dapatkan aturan validasi yang berlaku untuk request ini.
     */
    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:customers,id',
            'a_type_engine_id' => 'required|integer|exists:a_type_engines,id', // <-- Ubah ke integer
            'b_merk_id' => 'required|integer|exists:b_merks,id', // <-- Ubah ke integer
            'c_type_chassis_id' => 'required|integer|exists:c_type_chassis,id', // <-- Ubah ke integer
            'd_jenis_kendaraan_id' => 'required|integer|exists:d_jenis_kendaraan,id', // <-- Ubah ke integer
            'f_pengajuan_id' => 'required|integer|exists:f_pengajuan,id',
        ];
    }
}
