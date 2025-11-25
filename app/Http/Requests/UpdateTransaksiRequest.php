<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransaksiRequest extends FormRequest
{
    /**
     * Tentukan apakah user diizinkan membuat request ini.
     * Otorisasi sebenarnya akan ditangani oleh Policy di Controller.
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
            // Ganti 4 field terpisah dengan 1 field master_data_id
            'master_data_id' => 'required|integer|exists:master_data,id',
            'f_pengajuan_id' => 'required|integer|exists:f_pengajuan,id',
        ];
    }
}
