<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // <-- TAMBAHKAN INI

class UpdateMerkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $merkId = $this->route('merk')->id;

        return [
            'merk' => [ // <-- Ubah menjadi array
                'required',
                'string',
                'max:255',
                Rule::unique('b_merks')->whereNull('deleted_at')->ignore($merkId),
            ],
        ];
    }
}
