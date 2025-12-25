<?php

namespace App\Support\DTO\Documents;

use Carbon\CarbonInterface;

readonly class DocumentDTO
{
    /**
     * @param array<string, mixed> $dataJson
     * @param array<int, DocumentRouteStepDTO> $route
     */
    public function __construct(
        public int $id,
        public string $type,
        public string $number,
        public CarbonInterface $date,
        public string $status,
        public int $templateId,
        public array $dataJson,
        public int $createdBy,
        public string $verifyHash,
        public CarbonInterface $createdAt,
        public CarbonInterface $updatedAt,
        public array $route = [],
    ) {
    }
}


