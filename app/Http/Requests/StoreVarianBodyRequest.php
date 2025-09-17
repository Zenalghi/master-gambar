<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVarianBodyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jenis_kendaraan_id' => 'required|string|size:9|exists:d_jenis_kendaraan,id',
            'varian_body' => [
                'required',
                'string',
                'max:255',
                // Pastikan nama Varian Body unik untuk Jenis Kendaraan yang sama
                Rule::unique('e_varian_body')->where('jenis_kendaraan_id', $this->jenis_kendaraan_id),
            ],
        ];
    }
}