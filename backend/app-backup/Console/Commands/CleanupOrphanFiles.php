<?php

namespace App\Console\Commands;

use App\Services\FileHygieneService;
use Illuminate\Console\Command;

class CleanupOrphanFiles extends Command
{
    protected $signature = 'files:cleanup-orphans';

    protected $description = 'Удалить orphan файлы';

    public function handle(FileHygieneService $service): int
    {
        $deleted = $service->cleanupOrphanFiles();
        $this->info("Удалено orphan файлов: {$deleted}");
        return 0;
    }
}


