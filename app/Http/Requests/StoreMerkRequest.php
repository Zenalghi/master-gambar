<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Gabungkan parent_id dan merk_code untuk cek keunikan
        $compositeId = $this->input('type_engine_id') . $this->input('merk_code');

        return [
            'type_engine_id' => 'required|string|size:2|exists:a_type_engines,id',
            'merk_code' => [
                'required',
                'string',
                'size:2',
                // Pastikan kombinasi engine+merk belum ada
                Rule::unique('b_merks', 'id')->where(function ($query) use ($compositeId) {
                    return $query->where('id', $compositeId);
                }),
            ],
            'merk' => 'required|string|max:255',
        ];
    }
}
