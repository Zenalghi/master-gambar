<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Validasi keberadaan ID di tabel induk
            'a_type_engine_id' => 'required|exists:a_type_engines,id',
            'b_merk_id' => 'required|exists:b_merks,id',
            'c_type_chassis_id' => 'required|exists:c_type_chassis,id',

            // Validasi Khusus untuk Kombinasi Unik
            'd_jenis_kendaraan_id' => [
                'required',
                'exists:d_jenis_kendaraan,id',
                // Cek apakah kombinasi 4 ID ini sudah ada di database?
                Rule::unique('master_data')->where(function ($query) {
                    return $query->where('a_type_engine_id', $this->a_type_engine_id)
                        ->where('b_merk_id', $this->b_merk_id)
                        ->where('c_type_chassis_id', $this->c_type_chassis_id);
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // Pesan error khusus yang akan muncul di Snackbar Flutter
            'd_jenis_kendaraan_id.unique' => 'Kombinasi Master Data ini (Engine, Merk, Chassis, Jenis) sudah ada di database.',
        ];
    }

    // Opsional: Rename atribut agar pesan default lebih enak dibaca
    public function attributes()
    {
        return [
            'a_type_engine_id' => 'Type Engine',
            'b_merk_id' => 'Merk',
            'c_type_chassis_id' => 'Type Chassis',
            'd_jenis_kendaraan_id' => 'Jenis Kendaraan',
        ];
    }
}
