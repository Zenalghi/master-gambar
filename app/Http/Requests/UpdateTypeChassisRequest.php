<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTypeChassisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $chassisId = $this->route('typeChassis')->id;

        return [
            'type_chassis' => [
                'required',
                'string',
                'max:255',
                // Cek unik, abaikan ID saat ini dan yang sudah di-soft-delete
                Rule::unique('c_type_chassis')->whereNull('deleted_at')->ignore($chassisId),
            ],
            'sut_pdf' => 'nullable|file|mimes:pdf|max:2048',
            'remove_sut_pdf' => 'nullable|string',
        ];
    }
}
