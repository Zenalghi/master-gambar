<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            // Kita hanya perlu memvalidasi input dari pengguna
            'type_engine_id' => 'required|string|size:2|exists:a_type_engines,id',
            'merk' => 'required|string|max:255',

            // Validasi untuk 'merk_code' dihapus karena sekarang dibuat otomatis oleh controller
        ];
    }
}
