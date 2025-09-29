<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJenisKendaraanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type_chassis_id' => 'required|string|size:7|exists:c_type_chassis,id',
            'jenis_kendaraan' => 'required|string|max:255',
        ];
    }
}
