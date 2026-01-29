<?php

namespace App\Observers;

use App\Models\Risk;
use App\Services\StudentTimelineService;

class RiskObserver
{
    public function __construct(
        private StudentTimelineService $timelineService
    ) {}

    public function updated(Risk $risk): void
    {
        // Записываем только если уровень риска red или yellow
        if ($risk->user_id && in_array($risk->level, ['red', 'yellow'])) {
            $levelNames = [
                'red' => 'Красный',
                'yellow' => 'Жёлтый',
            ];
            
            $this->timelineService->record('risk.updated', $risk->user_id, [
                'title' => "Риск: {$levelNames[$risk->level]}",
                'description' => $risk->description ?? "Уровень риска: {$risk->level}, балл: {$risk->score}",
                'related_entity_type' => Risk::class,
                'related_entity_id' => $risk->id,
                'payload' => [
                    'level' => $risk->level,
                    'score' => $risk->score,
                    'risk_type' => $risk->risk_type,
                    'description' => $risk->description,
                ],
                'event_date' => $risk->updated_at,
            ]);
        }
    }
}

