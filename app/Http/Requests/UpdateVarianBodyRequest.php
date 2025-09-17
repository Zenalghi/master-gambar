<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVarianBodyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Dapatkan ID Varian Body dari rute, misalnya /api/varian-body/123
        $varianBodyId = $this->route('varian_body')->id;

        return [
            'jenis_kendaraan_id' => 'required|string|size:9|exists:d_jenis_kendaraan,id',
            'varian_body' => [
                'required',
                'string',
                'max:255',
                // Unik, tapi abaikan untuk Varian Body yang sedang di-update
                Rule::unique('e_varian_body')
                    ->where('jenis_kendaraan_id', $this->jenis_kendaraan_id)
                    ->ignore($varianBodyId),
            ],
        ];
    }
}