<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTypeChassisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Gabungkan ID induk (merk) dan kode baru (chassis) untuk validasi keunikan
        $compositeId = $this->input('merk_id') . $this->input('chassis_code');

        return [
            'merk_id' => 'required|string|size:4|exists:b_merks,id',
            'chassis_code' => [
                'required',
                'string',
                'size:3',
                // Pastikan ID komposit yang baru belum pernah ada di database
                Rule::unique('c_type_chassis', 'id')->where(function ($query) use ($compositeId) {
                    return $query->where('id', $compositeId);
                }),
            ],
            'type_chassis' => 'required|string|max:255',
        ];
    }
}