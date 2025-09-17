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
        $compositeId = $this->input('type_chassis_id') . $this->input('jenis_kendaraan_code');

        return [
            'type_chassis_id' => 'required|string|size:7|exists:c_type_chassis,id',
            'jenis_kendaraan_code' => [
                'required',
                'string',
                'size:2',
                Rule::unique('d_jenis_kendaraan', 'id')->where(function ($query) use ($compositeId) {
                    return $query->where('id', $compositeId);
                }),
            ],
            'jenis_kendaraan' => 'required|string|max:255',
        ];
    }
}