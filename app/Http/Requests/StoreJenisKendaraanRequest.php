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
            'jenis_kendaraan' => [
                'required',
                'string',
                'max:255',
                // Unique check ignoring soft-deleted records
                Rule::unique('d_jenis_kendaraan')->whereNull('deleted_at'),
            ],
            'alias_kendaraan' => 'nullable|string|max:255',
        ];
    }
}
