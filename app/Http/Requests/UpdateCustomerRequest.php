<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')->id;
        return [
            // Pastikan nama PT unik, kecuali untuk dirinya sendiri saat update
            'nama_pt' => 'sometimes|required|string|max:255|unique:customers,nama_pt,' . $customerId,
            'pj' => 'sometimes|required|string|max:255',
            'signature_pj' => 'nullable|string',
        ];
    }
}
