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
        return [
            'merk_id' => 'required|integer|exists:b_merks,id', // <-- Ubah ke integer
            'type_chassis' => [ // <-- Ubah menjadi array
                'required',
                'string',
                'max:255',
                Rule::unique('c_type_chassis')->whereNull('deleted_at'),
            ],
        ];
    }
}
