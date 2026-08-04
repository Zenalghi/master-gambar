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
            // HAPUS 'merk_id', tidak lagi dibutuhkan
            'type_chassis' => [
                'required',
                'string',
                'max:255',
                // Cek unik hanya pada data yang tidak di-soft-delete
                Rule::unique('c_type_chassis')->whereNull('deleted_at'),
            ],
            'merek_dagang' => 'nullable|string|max:255',
            'jenis_tipe' => 'nullable|string|max:255',
            'sut_file' => 'nullable|file|mimes:pdf|max:500',
        ];
    }
}
