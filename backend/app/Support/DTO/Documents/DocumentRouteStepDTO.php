<?php

namespace App\Support\DTO\Documents;

use Carbon\CarbonInterface;

readonly class DocumentRouteStepDTO
{
    public function __construct(
        public int $id,
        public int $documentId,
        public int $stepNo,
        public ?int $approverRoleId,
        public ?int $approverUserId,
        public string $status,
        public ?CarbonInterface $decidedAt,
        public ?string $comment,
    ) {
    }
}


