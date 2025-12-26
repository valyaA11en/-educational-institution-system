<?php

namespace App\Support\DTO\Auth;

readonly class LoginRequestDTO
{
    public function __construct(
        public string $emailOrPhone,
        public string $password,
    ) {
    }
}



