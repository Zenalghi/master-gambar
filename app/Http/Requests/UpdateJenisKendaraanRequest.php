<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJenisKendaraanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $jenisKendaraanId = $this->route('jenis_kendaraan')->id;

        return [
            'jenis_kendaraan' => [ // <-- Ubah menjadi array
                'required',
                'string',
                'max:255',
                Rule::unique('d_jenis_kendaraan')->whereNull('deleted_at')->ignore($jenisKendaraanId),
            ],
        ];
    }
}
