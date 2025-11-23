<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVarianBodyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'master_data_id' => 'required|integer|exists:master_data,id',
            'varian_body' => [
                'required',
                'string',
                'max:255',
                Rule::unique('e_varian_body')
                    ->where('master_data_id', $this->master_data_id) 
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
