<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTypeChassisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Saat update, kita hanya mengizinkan perubahan nama deskriptifnya
        return [
            'type_chassis' => 'required|string|max:255',
        ];
    }
}