<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTypeChassisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $chassisId = $this->route('typeChassis')->id;

        return [
            'type_chassis' => [
                'required',
                'string',
                'max:255',
                // Cek unik kombinasi, abaikan ID saat ini dan yang sudah di-soft-delete
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
                })->ignore($chassisId),
            ],
            'nomor_sut' => 'nullable|string|max:255|unique:c_type_chassis,nomor_sut,' . $chassisId,
            'merek_dagang' => 'nullable|string|max:255',
            'jenis_tipe' => 'nullable|string|max:255',
            'sut_file' => 'nullable|file|mimes:pdf|max:500',
            'remove_sut_file' => 'nullable|string',
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
