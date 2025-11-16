<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // <-- TAMBAHKAN INI

class StoreMerkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Asumsikan hanya admin yang bisa akses route ini
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type_engine_id' => 'required|integer|exists:a_type_engines,id', // <-- Ubah ke integer
            'merk' => [ // <-- Ubah menjadi array
                'required',
                'string',
                'max:255',
                Rule::unique('b_merks')->whereNull('deleted_at'),
            ],
        ];
    }
}
