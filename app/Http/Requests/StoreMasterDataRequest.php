<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\MasterData;

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
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Hanya cek ketika semua nilai tersedia
            if ($this->filled('a_type_engine_id') && $this->filled('b_merk_id') && $this->filled('c_type_chassis_id') && $this->filled('d_jenis_kendaraan_id')) {
                $exists = MasterData::whereNull('deleted_at')
                    ->where('a_type_engine_id', $this->a_type_engine_id)
                    ->where('b_merk_id', $this->b_merk_id)
                    ->where('c_type_chassis_id', $this->c_type_chassis_id)
                    ->where('d_jenis_kendaraan_id', $this->d_jenis_kendaraan_id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('general', 'Data sudah ada.');
                }
            }
        });
    }
}
