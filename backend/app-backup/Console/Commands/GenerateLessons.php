<?php

namespace App\Console\Commands;

use App\Services\LessonGeneratorService;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateLessons extends Command
{
    protected $signature = 'lessons:generate
                            {--days=14 : Количество дней вперёд}
                            {--tenant= : ID тенанта (опционально)}
                            {--version= : ID версии расписания (опционально)}';

    protected $description = 'Генерировать уроки из расписания на указанное количество дней вперёд';

    public function handle(LessonGeneratorService $service): int
    {
        $days = (int) $this->option('days');
        $tenantId = $this->option('tenant');
        $versionId = $this->option('version');

        $from = Carbon::today();
        $to = Carbon::today()->addDays($days);

        if ($tenantId) {
            $tenants = [Tenant::findOrFail($tenantId)];
        } else {
            $tenants = Tenant::all();
        }

        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->info("Генерация уроков для тенанта: {$tenant->name} (ID: {$tenant->id})");
            
            try {
                $created = $service->generateForDateRange(
                    $tenant->id,
                    $from,
                    $to,
                    $versionId ? (int) $versionId : null
                );
                
                $totalCreated += $created;
                $this->info("Создано уроков: {$created}");
            } catch (\Exception $e) {
                $this->error("Ошибка при генерации уроков для тенанта {$tenant->id}: " . $e->getMessage());
            }
        }

        $this->info("Всего создано уроков: {$totalCreated}");

        return 0;
    }
}


