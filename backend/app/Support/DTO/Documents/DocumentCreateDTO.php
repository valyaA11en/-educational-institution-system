<?php

namespace App\Support\DTO\Documents;

readonly class DocumentCreateDTO
{
    public function __construct(
        public string $type,
        public int $templateId,
        public array $dataJson,
    ) {
    }
}


