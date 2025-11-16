<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVarianBodyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $varianBodyId = $this->route('varian_body')->id;

        return [
            'master_data_id' => 'required|integer|exists:master_data,id', // <-- BERUBAH
            'varian_body' => [
                'required',
                'string',
                'max:255',
                Rule::unique('e_varian_body')
                    ->where('master_data_id', $this->master_data_id) // <-- BERUBAH
                    ->whereNull('deleted_at')
                    ->ignore($varianBodyId),
            ],
        ];
    }
}
