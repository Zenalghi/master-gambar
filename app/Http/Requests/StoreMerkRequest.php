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
            'type_engine_id' => 'required|exists:a_type_engines,id', // Ganti ke integer jika ATypeEngine sudah diubah
            'merk' => [
                'required',
                'string',
                'max:255',
                // Aturan ini berarti:
                // "merk" harus unik di tabel "b_merks",
                // TAPI abaikan baris yang "deleted_at"-nya TIDAK null.
                Rule::unique('b_merks')->whereNull('deleted_at'),
            ],
        ];
    }
}
