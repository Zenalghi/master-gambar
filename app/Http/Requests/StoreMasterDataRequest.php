<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Asumsikan dihandle oleh middleware
    }
    public function rules(): array
    {
        return [
            'a_type_engine_id' => 'required|integer|exists:a_type_engines,id',
            'b_merk_id' => 'required|integer|exists:b_merks,id',
            'c_type_chassis_id' => 'required|integer|exists:c_type_chassis,id',
            'd_jenis_kendaraan_id' => 'required|integer|exists:d_jenis_kendaraan,id',

            // PERBAIKAN: Gunakan function($query) untuk kondisi where yang banyak
            Rule::unique('master_data')
                ->whereNull('deleted_at')
                ->where(function ($query) {
                    return $query->where('a_type_engine_id', $this->a_type_engine_id)
                        ->where('b_merk_id', $this->b_merk_id)
                        ->where('c_type_chassis_id', $this->c_type_chassis_id)
                        ->where('d_jenis_kendaraan_id', $this->d_jenis_kendaraan_id);
                }),
        ];
    }
}
