<?php

namespace App\Observers;

use App\Models\Document;
use App\Services\StudentPortfolioService;

class DocumentObserver
{
    public function __construct(
        private StudentPortfolioService $portfolioService
    ) {}

    public function created(Document $document): void
    {
        // Автоматически добавляем сертификаты в портфолио
        if ($document->type === 'certificate' || $document->type === 'сертификат') {
            $this->portfolioService->addFromCertificate($document);
        }
    }

    public function updated(Document $document): void
    {
        // Если тип изменился на сертификат, добавляем в портфолио
        if ($document->wasChanged('type') && ($document->type === 'certificate' || $document->type === 'сертификат')) {
            $this->portfolioService->addFromCertificate($document);
        }
    }
}


