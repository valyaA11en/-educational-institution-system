<?php

namespace App\Services\Schedule;

use App\Models\ScheduleVersion;
use App\Models\ScheduleItem;
use App\Models\ScheduleChangelog;
use App\Services\Outbox\OutboxService;
use App\Support\Events\EventTypes;
use Illuminate\Support\Facades\DB;

class ScheduleVersionService
{
    public function __construct(
        private OutboxService $outboxService
    ) {}

    public function createDraftVersion(int $termId, int $userId): ScheduleVersion
    {
        return DB::transaction(function () use ($termId, $userId) {
            $version = ScheduleVersion::create([
                'term_id' => $termId,
                'status' => 'draft',
                'created_by' => $userId,
            ]);

            $this->logChange($version->id, 'create', null, $userId, null, [
                'term_id' => $termId,
                'status' => 'draft',
            ]);

            return $version;
        });
    }

    public function publishVersion(int $versionId, int $userId): ScheduleVersion
    {
        return DB::transaction(function () use ($versionId, $userId) {
            $version = ScheduleVersion::findOrFail($versionId);

            if ($version->status !== 'draft') {
                throw new \Exception('Only draft versions can be published');
            }

            // Archive previous published version for the same term
            ScheduleVersion::where('term_id', $version->term_id)
                ->where('status', 'published')
                ->update(['status' => 'archived']);

            $version->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            $this->logChange($version->id, 'publish', null, $userId, [
                'status' => 'draft',
            ], [
                'status' => 'published',
                'published_at' => $version->published_at?->toIso8601String(),
            ]);

            // Dispatch outbox event
            $this->outboxService->record(
                EventTypes::SCHEDULE_VERSION_PUBLISHED,
                $userId,
                'schedule_version',
                $version->id,
                [
                    'term_id' => $version->term_id,
                    'version_id' => $version->id,
                ]
            );

            return $version->fresh();
        });
    }

    public function archiveVersion(int $versionId, int $userId): ScheduleVersion
    {
        return DB::transaction(function () use ($versionId, $userId) {
            $version = ScheduleVersion::findOrFail($versionId);

            if ($version->status === 'archived') {
                throw new \Exception('Version is already archived');
            }

            $beforeStatus = $version->status;

            $version->update(['status' => 'archived']);

            $this->logChange($version->id, 'archive', null, $userId, [
                'status' => $beforeStatus,
            ], [
                'status' => 'archived',
            ]);

            return $version->fresh();
        });
    }

    public function logItemChange(
        int $versionId,
        string $action,
        ?ScheduleItem $item,
        int $userId,
        ?array $before = null,
        ?array $after = null
    ): void {
        $this->logChange(
            $versionId,
            $action,
            $item?->id,
            $userId,
            $before ?? ($item ? $this->itemToArray($item) : null),
            $after ?? ($item ? $this->itemToArray($item) : null)
        );
    }

    private function logChange(
        int $versionId,
        string $action,
        ?int $scheduleItemId,
        int $userId,
        ?array $before = null,
        ?array $after = null
    ): void {
        ScheduleChangelog::create([
            'version_id' => $versionId,
            'action' => $action,
            'schedule_item_id' => $scheduleItemId,
            'actor_user_id' => $userId,
            'before_json' => $before,
            'after_json' => $after,
        ]);
    }

    private function itemToArray(ScheduleItem $item): array
    {
        return [
            'id' => $item->id,
            'version_id' => $item->version_id,
            'date' => $item->date?->format('Y-m-d'),
            'time_slot_id' => $item->time_slot_id,
            'group_id' => $item->group_id,
            'subgroup_id' => $item->subgroup_id,
            'subject_id' => $item->subject_id,
            'teacher_user_id' => $item->teacher_user_id,
            'room_id' => $item->room_id,
            'lesson_type' => $item->lesson_type,
        ];
    }

    public function getChanges(int $versionId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = ScheduleChangelog::where('version_id', $versionId)
            ->with(['actor', 'scheduleItem'])
            ->orderBy('created_at', 'desc');

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        return $query->get()->toArray();
    }

    public function getDiff(int $fromVersionId, int $toVersionId): array
    {
        $fromItems = ScheduleItem::where('version_id', $fromVersionId)
            ->get()
            ->keyBy(function ($item) {
                return $this->getItemKey($item);
            });

        $toItems = ScheduleItem::where('version_id', $toVersionId)
            ->get()
            ->keyBy(function ($item) {
                return $this->getItemKey($item);
            });

        $added = [];
        $removed = [];
        $changed = [];

        foreach ($toItems as $key => $toItem) {
            if (!isset($fromItems[$key])) {
                $added[] = $this->itemToArray($toItem);
            } else {
                $fromItem = $fromItems[$key];
                if ($this->itemsDiffer($fromItem, $toItem)) {
                    $changed[] = [
                        'from' => $this->itemToArray($fromItem),
                        'to' => $this->itemToArray($toItem),
                    ];
                }
            }
        }

        foreach ($fromItems as $key => $fromItem) {
            if (!isset($toItems[$key])) {
                $removed[] = $this->itemToArray($fromItem);
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    private function getItemKey(ScheduleItem $item): string
    {
        return sprintf(
            '%s_%s_%s_%s_%s',
            $item->date?->format('Y-m-d') ?? '',
            $item->time_slot_id ?? '',
            $item->group_id ?? '',
            $item->subgroup_id ?? '',
            $item->subject_id ?? ''
        );
    }

    private function itemsDiffer(ScheduleItem $from, ScheduleItem $to): bool
    {
        return $from->teacher_user_id !== $to->teacher_user_id
            || $from->room_id !== $to->room_id
            || $from->lesson_type !== $to->lesson_type;
    }

    // Legacy methods for backward compatibility
    public function publish(ScheduleVersion $version): void
    {
        $this->publishVersion($version->id, auth()->id());
    }

    public function archive(ScheduleVersion $version): void
    {
        $this->archiveVersion($version->id, auth()->id());
    }

    public function getChangelog(ScheduleVersion $version, ?int $itemId = null): array
    {
        $query = ScheduleChangelog::with(['actor', 'scheduleItem'])
            ->where('version_id', $version->id)
            ->orderBy('created_at', 'desc');

        if ($itemId) {
            $query->where('schedule_item_id', $itemId);
        }

        return $query->get()->toArray();
    }

    public function compareVersions(ScheduleVersion $version1, ScheduleVersion $version2): array
    {
        return $this->getDiff($version1->id, $version2->id);
    }
}
