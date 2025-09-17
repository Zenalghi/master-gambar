<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nama_pt' => 'required|string|max:255|unique:customers,nama_pt',
            'pj' => 'required|string|max:255',
            'signature_pj' => 'nullable|string', // atau 'image|mimes:png,jpg' jika upload file
        ];
    }
}