<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMerkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Saat update, kita hanya boleh mengubah namanya
        return [
            'merk' => 'required|string|max:255',
        ];
    }
}
