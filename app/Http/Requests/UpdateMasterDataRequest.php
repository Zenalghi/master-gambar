<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\MasterData;
use Illuminate\Validation\ValidationException;

class UpdateMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 1. Coba ambil parameter dengan nama yang kita tentukan di api.php
        $routeParam = $this->route('master_datum');

        // 2. Jika kosong, coba ambil dengan nama default Laravel (master_data)
        if (!$routeParam) {
            $routeParam = $this->route('master_data');
        }

        // 3. Ekstrak ID:
        // Jika $routeParam adalah Object Model, ambil ->id.
        // Jika bukan (misal string "1"), maka itu adalah ID-nya.
        $masterDataId = ($routeParam instanceof \App\Models\MasterData) ? $routeParam->id : $routeParam;

        return [
            'a_type_engine_id' => 'required|integer|exists:a_type_engines,id',
            'b_merk_id' => 'required|integer|exists:b_merks,id',
            'c_type_chassis_id' => 'required|integer|exists:c_type_chassis,id',
            'd_jenis_kendaraan_id' => 'required|integer|exists:d_jenis_kendaraan,id',

            // Gunakan $masterDataId yang sudah kita amankan
            \Illuminate\Validation\Rule::unique('master_data')->whereNull('deleted_at')->where(function ($query) {
                return $query->where('a_type_engine_id', $this->a_type_engine_id)
                    ->where('b_merk_id', $this->b_merk_id)
                    ->where('c_type_chassis_id', $this->c_type_chassis_id)
                    ->where('d_jenis_kendaraan_id', $this->d_jenis_kendaraan_id);
            })->ignore($masterDataId),
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Dapatkan model dari route atau dari ID
            $routeParam = $this->route('master_datum') ?: $this->route('master_data');
            $masterData = ($routeParam instanceof MasterData) ? $routeParam : MasterData::find($routeParam);

            if (!$masterData) {
                return;
            }

            // Pastikan semua field ada pada request sebelum membandingkan
            if ($this->filled('a_type_engine_id') && $this->filled('b_merk_id') && $this->filled('c_type_chassis_id') && $this->filled('d_jenis_kendaraan_id')) {
                $same = (int)$this->a_type_engine_id === (int)$masterData->a_type_engine_id
                    && (int)$this->b_merk_id === (int)$masterData->b_merk_id
                    && (int)$this->c_type_chassis_id === (int)$masterData->c_type_chassis_id
                    && (int)$this->d_jenis_kendaraan_id === (int)$masterData->d_jenis_kendaraan_id;

                if ($same) {
                    $validator->errors()->add('general', 'Tidak ada perubahan data.');
                }
            }
        });
    }
}
