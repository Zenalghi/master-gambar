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
        // Dapatkan ID Merk yang sedang diedit dari route
        $merkId = $this->route('merk')->id;

        return [
            'merk' => [
                'required',
                'string',
                'max:255',
                // Aturan ini berarti:
                // "merk" harus unik, tapi abaikan baris yang 'deleted_at'-nya tidak null,
                // DAN abaikan juga baris dengan ID yang sedang kita edit ini.
                Rule::unique('b_merks')->whereNull('deleted_at')->ignore($merkId),
            ],
        ];
    }
}
