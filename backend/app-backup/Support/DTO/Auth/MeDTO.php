<?php

namespace App\Support\DTO\Auth;

use App\Models\User;

readonly class MeDTO
{
    /**
     * @param array<int, string> $roles
     * @param array<int, string> $permissions
     */
    public function __construct(
        public User $user,
        public array $roles,
        public array $permissions,
    ) {
    }
}









