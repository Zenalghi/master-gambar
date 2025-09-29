<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTypeChassisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merk_id' => 'required|string|size:4|exists:b_merks,id',
            'type_chassis' => 'required|string|max:255',
        ];
    }
}
