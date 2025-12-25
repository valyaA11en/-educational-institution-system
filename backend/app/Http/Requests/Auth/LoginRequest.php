<?php

namespace App\Http\Requests\Auth;

use App\Support\DTO\Auth\LoginRequestDTO;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_or_phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function toDTO(): LoginRequestDTO
    {
        $data = $this->validated();

        return new LoginRequestDTO(
            emailOrPhone: $data['email_or_phone'],
            password: $data['password'],
        );
    }
}


