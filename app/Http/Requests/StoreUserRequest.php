<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Diizinkan karena sudah dilindungi middleware admin
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:8|confirmed', // 'confirmed' berarti harus ada field 'password_confirmation'
            'role_id' => 'required|exists:roles,id',
        ];
    }
}
