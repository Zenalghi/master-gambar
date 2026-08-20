<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTypeChassisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // HAPUS 'merk_id', tidak lagi dibutuhkan
            'type_chassis' => [
                'required',
                'string',
                'max:255',
                // Cek unik kombinasi type_chassis, nomor_sut, merek_dagang, jenis_tipe pada data aktif
                Rule::unique('c_type_chassis')->where(function ($query) {
                    $nomorSut = $this->input('nomor_sut') ?: null;
                    $merekDagang = $this->input('merek_dagang') ?: null;
                    $jenisTipe = $this->input('jenis_tipe') ?: null;

                    if ($nomorSut === null) {
                        $query->whereNull('nomor_sut');
                    } else {
                        $query->where('nomor_sut', $nomorSut);
                    }

                    if ($merekDagang === null) {
                        $query->whereNull('merek_dagang');
                    } else {
                        $query->where('merek_dagang', $merekDagang);
                    }
                    
                    if ($jenisTipe === null) {
                        $query->whereNull('jenis_tipe');
                    } else {
                        $query->where('jenis_tipe', $jenisTipe);
                    }
                    
                    return $query->whereNull('deleted_at');
                }),
            ],
            'nomor_sut' => 'nullable|string|max:255|unique:c_type_chassis,nomor_sut',
            'merek_dagang' => 'nullable|string|max:255',
            'jenis_tipe' => 'nullable|string|max:255',
            'sut_file' => 'nullable|file|mimes:pdf|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'type_chassis.unique' => 'Kombinasi Type Chassis, Nomor SUT, Merek Dagang, dan Jenis Tipe sudah digunakan.',
            'nomor_sut.unique' => 'Nomor SUT sudah digunakan oleh Type Chassis lain.',
        ];
    }
}
