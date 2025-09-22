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
            'customer_id' => 'sometimes|required|exists:customers,id',
            'd_jenis_kendaraan_id' => 'sometimes|required|string|exists:d_jenis_kendaraan,id',
            'f_pengajuan_id' => 'sometimes|required|exists:f_pengajuan,id',
        ];
    }
}
