<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJenisKendaraanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'type_chassis_id' => 'required|integer|exists:c_type_chassis,id', // <-- Ubah ke integer
            'jenis_kendaraan' => [ // <-- Ubah menjadi array
                'required',
                'string',
                'max:255',
                Rule::unique('d_jenis_kendaraan')->whereNull('deleted_at'),
            ],
        ];
    }
}
