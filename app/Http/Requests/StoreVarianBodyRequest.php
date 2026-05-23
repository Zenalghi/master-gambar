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
            'master_data_id' => 'required|integer|exists:master_data,id',
            'varian_bodies'   => 'required|array|min:1',
            'varian_bodies.*' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'varian_bodies.required' => 'Minimal satu varian harus dipilih/diisi.',
        ];
    }
}
