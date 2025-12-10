<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Ambil ID data yang sedang di-update dari route binding
        // Pastikan nama parameternya sesuai route ('master_datum' atau 'master_data')
        // Di controller Anda pakai binding model 'masterDatum', jadi kita ambil ID-nya:
        $masterDataId = $this->route('master_datum')->id ?? $this->route('master_data');

        return [
            'a_type_engine_id' => 'required|exists:a_type_engines,id',
            'b_merk_id' => 'required|exists:b_merks,id',
            'c_type_chassis_id' => 'required|exists:c_type_chassis,id',
            'd_jenis_kendaraan_id' => [
                'required',
                'exists:d_jenis_kendaraan,id',
                Rule::unique('master_data')->where(function ($query) {
                    return $query->where('a_type_engine_id', $this->a_type_engine_id)
                        ->where('b_merk_id', $this->b_merk_id)
                        ->where('c_type_chassis_id', $this->c_type_chassis_id);
                })->ignore($masterDataId), // <--- PENTING: Abaikan ID data ini sendiri
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'd_jenis_kendaraan_id.unique' => 'Master Data dengan kombinasi ini sudah terdaftar.',
        ];
    }
}
