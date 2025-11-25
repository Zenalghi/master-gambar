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
            'master_data_id' => 'required|integer|exists:master_data,id',
            'f_pengajuan_id' => 'required|integer|exists:f_pengajuan,id',
        ];
    }
}
