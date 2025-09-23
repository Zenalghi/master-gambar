<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Dapatkan ID user dari rute untuk aturan 'unique'
        $userId = $this->route('user')->id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255|unique:users,username,' . $userId,
            'password' => 'sometimes|nullable|string|min:8|confirmed', // Password tidak wajib diubah
            'role_id' => 'required|exists:roles,id',
        ];
    }
}
