<?php

namespace App\Domains\Rules\Services;

use App\Models\Notification;
use App\Models\Risk;
use App\Models\Rule;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RuleEngineService
{
    /**
     * Handle event through rules
     */
    public function handleEvent(string $eventType, array $payload, ?int $actorUserId = null): void
    {
        // Load enabled rules matching event type
        $rules = Rule::where('enabled', true)
            ->whereJsonContains('conditions_json->event_type', $eventType)
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        foreach ($rules as $rule) {
            try {
                if ($this->matchesConditions($rule, $eventType, $payload, $actorUserId)) {
                    $this->executeActions($rule, $eventType, $payload, $actorUserId);
                }
            } catch (\Exception $e) {
                Log::error("Rule execution failed", [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'event_type' => $eventType,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Check if rule conditions match
     */
    private function matchesConditions(Rule $rule, string $eventType, array $payload, ?int $actorUserId): bool
    {
        $conditions = $rule->conditions_json ?? [];

        if (empty($conditions)) {
            return false;
        }

        // Check event_type
        if (isset($conditions['event_type']) && $conditions['event_type'] !== $eventType) {
            return false;
        }

        // Check filters
        if (isset($conditions['filters'])) {
            if (!$this->checkFilters($conditions['filters'], $payload, $actorUserId)) {
                return false;
            }
        }

        // Check thresholds
        if (isset($conditions['thresholds'])) {
            if (!$this->checkThresholds($conditions['thresholds'], $payload, $actorUserId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check filters (groupId, role, subjectId)
     */
    private function checkFilters(array $filters, array $payload, ?int $actorUserId): bool
    {
        if (isset($filters['groupId'])) {
            $payloadGroupId = $payload['group_id'] ?? $payload['groupId'] ?? null;
            if ($payloadGroupId != $filters['groupId']) {
                return false;
            }
        }

        if (isset($filters['subjectId'])) {
            $payloadSubjectId = $payload['subject_id'] ?? $payload['subjectId'] ?? null;
            if ($payloadSubjectId != $filters['subjectId']) {
                return false;
            }
        }

        if (isset($filters['role'])) {
            if (!$actorUserId) {
                return false;
            }
            $user = User::find($actorUserId);
            if (!$user) {
                return false;
            }
            $hasRole = $user->roles()->where('name', $filters['role'])->exists();
            if (!$hasRole) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check thresholds (avg<=2, absences>=3, ungraded_days>3, debts>=N)
     */
    private function checkThresholds(array $thresholds, array $payload, ?int $actorUserId): bool
    {
        if (isset($thresholds['avg'])) {
            $studentId = $payload['student_id'] ?? $payload['student_user_id'] ?? null;
            if (!$studentId) {
                return false;
            }

            $avg = $this->getStudentAverage($studentId, $thresholds['avg']['period_days'] ?? 30);
            $operator = $thresholds['avg']['operator'] ?? 'less_than_or_equal';
            $value = $thresholds['avg']['value'] ?? 2.5;

            if (!$this->compareThreshold($avg, $operator, $value)) {
                return false;
            }
        }

        if (isset($thresholds['absences'])) {
            $studentId = $payload['student_id'] ?? $payload['student_user_id'] ?? null;
            if (!$studentId) {
                return false;
            }

            $absences = $this->getStudentAbsences($studentId, $thresholds['absences']['period_days'] ?? 30);
            $operator = $thresholds['absences']['operator'] ?? 'greater_than_or_equal';
            $value = $thresholds['absences']['value'] ?? 3;

            if (!$this->compareThreshold($absences, $operator, $value)) {
                return false;
            }
        }

        if (isset($thresholds['ungraded_days'])) {
            $studentId = $payload['student_id'] ?? $payload['student_user_id'] ?? null;
            if (!$studentId) {
                return false;
            }

            $ungradedDays = $this->getUngradedDays($studentId);
            $operator = $thresholds['ungraded_days']['operator'] ?? 'greater_than';
            $value = $thresholds['ungraded_days']['value'] ?? 3;

            if (!$this->compareThreshold($ungradedDays, $operator, $value)) {
                return false;
            }
        }

        if (isset($thresholds['debts'])) {
            $studentId = $payload['student_id'] ?? $payload['student_user_id'] ?? null;
            if (!$studentId) {
                return false;
            }

            $debts = $this->getStudentDebts($studentId);
            $operator = $thresholds['debts']['operator'] ?? 'greater_than_or_equal';
            $value = $thresholds['debts']['value'] ?? 1;

            if (!$this->compareThreshold($debts, $operator, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare threshold value
     */
    private function compareThreshold(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'less_than' => $actual < $expected,
            'less_than_or_equal' => $actual <= $expected,
            'greater_than' => $actual > $expected,
            'greater_than_or_equal' => $actual >= $expected,
            'equals' => $actual == $expected,
            default => false,
        };
    }

    /**
     * Get student average for period
     */
    private function getStudentAverage(int $studentId, int $periodDays): float
    {
        $since = now()->subDays($periodDays);

        $avg = DB::table('grades')
            ->where('student_user_id', $studentId)
            ->where('created_at', '>=', $since)
            ->avg('value');

        return (float) ($avg ?? 0);
    }

    /**
     * Get student absences for period
     */
    private function getStudentAbsences(int $studentId, int $periodDays): int
    {
        $since = now()->subDays($periodDays);

        // TODO: implement when attendance table exists
        return 0;
    }

    /**
     * Get ungraded days count
     */
    private function getUngradedDays(int $studentId): int
    {
        // TODO: implement when assignments/submissions table exists
        return 0;
    }

    /**
     * Get student debts count
     */
    private function getStudentDebts(int $studentId): int
    {
        // TODO: implement when debts table exists
        return 0;
    }

    /**
     * Execute actions
     */
    private function executeActions(Rule $rule, string $eventType, array $payload, ?int $actorUserId): void
    {
        $actions = $rule->actions_json ?? [];

        foreach ($actions as $action) {
            try {
                $this->executeAction($action, $rule, $eventType, $payload, $actorUserId);
            } catch (\Exception $e) {
                Log::error("Action execution failed", [
                    'rule_id' => $rule->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Execute single action
     */
    private function executeAction(array $action, Rule $rule, string $eventType, array $payload, ?int $actorUserId): void
    {
        $type = $action['type'] ?? null;

        match ($type) {
            'create_notification' => $this->createNotification($action, $payload, $actorUserId),
            'create_ticket' => $this->createTicket($action, $payload, $actorUserId),
            'flag_risk' => $this->flagRisk($action, $payload, $actorUserId),
            'send_email' => $this->sendEmail($action, $payload, $actorUserId),
            default => Log::warning("Unknown action type: {$type}", ['rule_id' => $rule->id]),
        };
    }

    /**
     * Create notification action
     */
    private function createNotification(array $action, array $payload, ?int $actorUserId): void
    {
        $to = $action['to'] ?? null;
        $type = $action['notification_type'] ?? 'rule.triggered';
        $notificationPayload = $action['payload'] ?? [];

        $userIds = $this->resolveNotificationRecipients($to, $payload, $actorUserId);

        foreach ($userIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'payload_json' => array_merge([
                    'message' => $action['message'] ?? 'Rule triggered',
                    'event_payload' => $payload,
                ], $notificationPayload),
                'channel' => $action['channel'] ?? 'in_app',
                'status' => 'new',
            ]);
        }
    }

    /**
     * Resolve notification recipients
     */
    private function resolveNotificationRecipients(string $to, array $payload, ?int $actorUserId): array
    {
        $userIds = [];

        if ($to === 'student') {
            $studentId = $payload['student_id'] ?? $payload['student_user_id'] ?? null;
            if ($studentId) {
                $userIds[] = $studentId;
            }
        } elseif ($to === 'parent') {
            $studentId = $payload['student_id'] ?? $payload['student_user_id'] ?? null;
            $userIds = array_merge($userIds, $this->getParentIds($studentId));
        } elseif ($to === 'curator') {
            $groupId = $payload['group_id'] ?? $payload['groupId'] ?? null;
            $userIds = array_merge($userIds, $this->getCuratorIds($groupId));
        } elseif ($to === 'teacher') {
            $teacherId = $payload['teacher_id'] ?? $payload['teacher_user_id'] ?? null;
            if ($teacherId) {
                $userIds[] = $teacherId;
            }
        } elseif ($to === 'admin') {
            $userIds = array_merge($userIds, $this->getAdminIds());
        } elseif (is_numeric($to)) {
            $userIds[] = (int) $to;
        }

        return array_filter($userIds);
    }

    /**
     * Get parent IDs for student
     */
    private function getParentIds(?int $studentId): array
    {
        if (!$studentId) {
            return [];
        }

        return DB::table('user_links_parent_child')
            ->where('student_user_id', $studentId)
            ->where('status', 'approved')
            ->pluck('parent_user_id')
            ->toArray();
    }

    /**
     * Get curator IDs for group
     */
    private function getCuratorIds(?int $groupId): array
    {
        if (!$groupId) {
            return [];
        }

        return DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('role_in_group', 'curator')
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * Get admin IDs
     */
    private function getAdminIds(): array
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'admin')
            ->pluck('user_roles.user_id')
            ->toArray();
    }

    /**
     * Create ticket action
     */
    private function createTicket(array $action, array $payload, ?int $actorUserId): void
    {
        Ticket::create([
            'created_by' => $actorUserId ?? 1, // System user if no actor
            'category' => $action['category'] ?? 'general',
            'title' => $this->resolveTemplate($action['title'] ?? 'Ticket from rule', $payload),
            'description' => $this->resolveTemplate($action['description'] ?? '', $payload),
            'status' => 'open',
            'priority' => $action['priority'] ?? 'normal',
            'assigned_to' => $action['assigned_to'] ?? null,
        ]);
    }

    /**
     * Flag risk action
     */
    private function flagRisk(array $action, array $payload, ?int $actorUserId): void
    {
        $riskType = $action['risk_type'] ?? 'academic';
        $level = $action['level'] ?? 'medium';
        $entityType = $action['entity_type'] ?? 'user';
        $entityId = $action['entity_id'] ?? $payload['student_id'] ?? $payload['student_user_id'] ?? null;

        if (!$entityId) {
            return;
        }

        Risk::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'risk_level' => $level,
            'risk_score' => $this->calculateRiskScore($level),
            'factors' => [
                'risk_type' => $riskType,
                'triggered_by' => 'rule',
                'payload' => $payload,
            ],
            'calculated_by' => $actorUserId,
            'calculated_at' => now(),
        ]);
    }

    /**
     * Calculate risk score from level
     */
    private function calculateRiskScore(string $level): float
    {
        return match ($level) {
            'critical' => 9.0,
            'high' => 7.0,
            'medium' => 5.0,
            'low' => 3.0,
            default => 5.0,
        };
    }

    /**
     * Send email action (TODO)
     */
    private function sendEmail(array $action, array $payload, ?int $actorUserId): void
    {
        // TODO: Implement email sending
        Log::info("Email action triggered", [
            'action' => $action,
            'payload' => $payload,
        ]);
    }

    /**
     * Resolve template string with payload values
     */
    private function resolveTemplate(string $template, array $payload): string
    {
        foreach ($payload as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }
}

