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
        $chassisId = $this->route('typeChassis')->id; // Sesuai 'parameters' di api.php

        return [
            'type_chassis' => [ // <-- Ubah menjadi array
                'required',
                'string',
                'max:255',
                Rule::unique('c_type_chassis')->whereNull('deleted_at')->ignore($chassisId),
            ],
        ];
    }
}
