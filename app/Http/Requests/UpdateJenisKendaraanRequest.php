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
            'jenis_kendaraan' => [
                'required',
                'string',
                'max:255',
                // Unique check ignoring soft-deleted records and the current record
                Rule::unique('d_jenis_kendaraan')->whereNull('deleted_at')->ignore($jenisKendaraanId),
            ],
            'alias_kendaraan' => 'nullable|string|max:255',
        ];
    }
}
