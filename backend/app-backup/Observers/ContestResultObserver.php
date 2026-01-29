<?php

namespace App\Observers;

use App\Models\ContestResult;
use App\Services\StudentPortfolioService;

class ContestResultObserver
{
    public function __construct(
        private StudentPortfolioService $portfolioService
    ) {}

    public function created(ContestResult $result): void
    {
        // Автоматически добавляем в портфолио призовые места
        $this->portfolioService->addFromContestResult($result);
    }

    public function updated(ContestResult $result): void
    {
        // Если место изменилось и стало призовым, добавляем в портфолио
        if ($result->wasChanged('place') && $result->place && $result->place <= 3) {
            $this->portfolioService->addFromContestResult($result);
        }
    }
}


