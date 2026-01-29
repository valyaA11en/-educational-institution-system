<?php

namespace App\Support\DTO\Audit;

use Carbon\CarbonInterface;

readonly class AuditDTO
{
    /**
     * @param array<string, mixed>|null $beforeJson
     * @param array<string, mixed>|null $afterJson
     */
    public function __construct(
        public int $id,
        public ?int $tenantId,
        public ?int $userId,
        public string $action,
        public string $entity,
        public ?int $entityId,
        public ?array $beforeJson,
        public ?array $afterJson,
        public ?string $ip,
        public CarbonInterface $createdAt,
        public ?string $userFio = null,
        public ?string $userEmail = null,
    ) {
    }
}


