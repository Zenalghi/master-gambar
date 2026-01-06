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
        $routeParam = $this->route('varian_body');
        $varianBodyId = $routeParam instanceof \App\Models\EVarianBody ? $routeParam->id : $routeParam;

        return [
            'master_data_id' => 'required|integer|exists:master_data,id',
            'varian_body' => [
                'required',
                'string',
                'max:255',
                Rule::unique('e_varian_body')
                    ->where('master_data_id', $this->master_data_id)
                    ->ignore($varianBodyId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'varian_body.unique' => 'Nama Varian Body ini sudah terdaftar.',
        ];
    }
}
