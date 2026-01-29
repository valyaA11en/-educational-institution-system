<?php

namespace App\Support\DTO\Auth;

readonly class AuthTokensDTO
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {
    }
}



