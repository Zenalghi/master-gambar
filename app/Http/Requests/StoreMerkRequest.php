<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merk' => [
                'required',
                'string',
                'max:255',
                Rule::unique('b_merks')->whereNull('deleted_at'),
            ],
        ];
    }
}
