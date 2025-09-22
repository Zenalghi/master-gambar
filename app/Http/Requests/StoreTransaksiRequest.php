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
            'customer_id' => 'required|exists:customers,id',
            'd_jenis_kendaraan_id' => 'required|string|exists:d_jenis_kendaraan,id',
            'f_pengajuan_id' => 'required|exists:f_pengajuan,id',
        ];
    }
}
