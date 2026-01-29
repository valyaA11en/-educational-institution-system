<?php

namespace App\Services;

use App\Models\Rule;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Document;
use App\Notifications\RuleActionNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class RuleEngineService
{
    /**
     * Evaluate rules for a given event
     */
    public function evaluate(string $scope, array $context): array
    {
        $query = Rule::enabled()->forScope($scope);
        $rules = $query->get();
        $executedActions = [];

        foreach ($rules as $rule) {
            if ($this->validateConditions($rule->conditions_json ?? [], $context)) {
                $actions = $this->executeActions($rule->actions_json ?? [], $context);
                $executedActions[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'actions' => $actions,
                ];
            }
        }

        return $executedActions;
    }

    /**
     * Validate rule conditions against context
     */
    protected function validateConditions(array $conditions, array $context): bool
    {
        if (empty($conditions)) {
            return true; // No conditions = always true
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false; // All conditions must be true (AND logic)
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return false;
        }

        $contextValue = $this->getNestedValue($context, $field);

        return match ($operator) {
            'equals' => $contextValue == $value,
            'not_equals' => $contextValue != $value,
            'greater_than' => $contextValue > $value,
            'less_than' => $contextValue < $value,
            'greater_or_equal' => $contextValue >= $value,
            'less_or_equal' => $contextValue <= $value,
            'contains' => is_string($contextValue) && str_contains($contextValue, $value),
            'not_contains' => is_string($contextValue) && !str_contains($contextValue, $value),
            'in' => in_array($contextValue, (array)$value),
            'not_in' => !in_array($contextValue, (array)$value),
            'exists' => isset($contextValue) && $contextValue !== null,
            'not_exists' => !isset($contextValue) || $contextValue === null,
            default => false,
        };
    }

    /**
     * Get nested value from context using dot notation
     */
    protected function getNestedValue(array $context, string $path)
    {
        $keys = explode('.', $path);
        $value = $context;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Execute rule actions
     */
    protected function executeActions(array $actions, array $context): array
    {
        $results = [];

        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            $params = $action['params'] ?? [];

            try {
                $result = match ($type) {
                    'assign_user' => $this->actionAssignUser($params, $context),
                    'change_status' => $this->actionChangeStatus($params, $context),
                    'send_notification' => $this->actionSendNotification($params, $context),
                    'notification' => $this->actionSendNotification($params, $context), // Alias
                    'update_field' => $this->actionUpdateField($params, $context),
                    'create_record' => $this->actionCreateRecord($params, $context),
                    'log_event' => $this->actionLogEvent($params, $context),
                    default => ['success' => false, 'message' => "Unknown action type: {$type}"],
                };

                $results[] = [
                    'type' => $type,
                    'success' => $result['success'] ?? true,
                    'message' => $result['message'] ?? 'Action executed',
                    'data' => $result['data'] ?? null,
                ];
            } catch (\Exception $e) {
                Log::error("Rule action execution failed", [
                    'action' => $action,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $results[] = [
                    'type' => $type,
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Action: Assign user
     */
    protected function actionAssignUser(array $params, array $context): array
    {
        $userId = $params['user_id'] ?? null;
        $entityType = $context['entity_type'] ?? null;
        $entityId = $context['entity_id'] ?? null;

        if (!$userId || !$entityType || !$entityId) {
            return ['success' => false, 'message' => 'Missing required parameters'];
        }

        // Example: Update ticket assigned_to
        if ($entityType === 'ticket') {
            $ticket = \App\Models\Ticket::find($entityId);
            if ($ticket) {
                $ticket->assigned_to = $userId;
                $ticket->save();
                return ['success' => true, 'message' => "Assigned user {$userId} to ticket {$entityId}"];
            }
        }

        return ['success' => false, 'message' => 'Entity not found or not supported'];
    }

    /**
     * Action: Change status
     */
    protected function actionChangeStatus(array $params, array $context): array
    {
        $status = $params['status'] ?? null;
        $entityType = $context['entity_type'] ?? null;
        $entityId = $context['entity_id'] ?? null;

        if (!$status || !$entityType || !$entityId) {
            return ['success' => false, 'message' => 'Missing required parameters'];
        }

        if ($entityType === 'ticket') {
            $ticket = \App\Models\Ticket::find($entityId);
            if ($ticket) {
                $ticket->status = $status;
                $ticket->save();
                return ['success' => true, 'message' => "Changed ticket {$entityId} status to {$status}"];
            }
        }

        return ['success' => false, 'message' => 'Entity not found or not supported'];
    }

    /**
     * Action: Send notification
     * 
     * Params:
     * - title: string (required) - Notification title
     * - message: string (required) - Notification message
     * - users: array|int (optional) - User IDs or 'all' or 'admins' or 'entity_creator' or 'entity_assigned'
     * - channels: array (optional) - ['database', 'mail', 'broadcast'] (default: ['database'])
     * - data: array (optional) - Additional data to include
     */
    protected function actionSendNotification(array $params, array $context): array
    {
        $title = $params['title'] ?? 'Rule Action Notification';
        $message = $params['message'] ?? 'A rule action has been executed';
        $channels = $params['channels'] ?? ['database'];
        $data = $params['data'] ?? [];
        $usersParam = $params['users'] ?? null;

        // Merge context data into notification data
        $notificationData = array_merge($data, [
            'context' => $context,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Determine recipients
        $users = $this->resolveNotificationRecipients($usersParam, $context);

        if (empty($users)) {
            return ['success' => false, 'message' => 'No recipients found'];
        }

        $notification = new RuleActionNotification($title, $message, $notificationData, $channels);

        try {
            // Send notification to all recipients
            Notification::send($users, $notification);

            return [
                'success' => true,
                'message' => "Notification sent to " . count($users) . " user(s)",
                'data' => [
                    'recipients_count' => count($users),
                    'channels' => $channels,
                ],
            ];
        } catch (\Exception $e) {
            Log::error("Failed to send notification", [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);

            return [
                'success' => false,
                'message' => "Failed to send notification: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve notification recipients based on params and context
     */
    protected function resolveNotificationRecipients($usersParam, array $context): array
    {
        if ($usersParam === null) {
            // Default: notify entity creator or assigned user
            return $this->getDefaultRecipients($context);
        }

        if ($usersParam === 'all') {
            // Notify all active users in tenant
            $tenantId = $context['tenant_id'] ?? null;
            $query = User::where('status', 'active');
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            return $query->get()->all();
        }

        if ($usersParam === 'admins') {
            // Notify users with admin role
            $tenantId = $context['tenant_id'] ?? null;
            $query = User::where('status', 'active')
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'admin');
                });
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            $users = $query->get();
            return $users->all();
        }

        if ($usersParam === 'entity_creator') {
            $entityType = $context['entity_type'] ?? null;
            $entityId = $context['entity_id'] ?? null;
            $userId = $this->getEntityCreatorId($entityType, $entityId);
            return $userId ? [User::find($userId)] : [];
        }

        if ($usersParam === 'entity_assigned') {
            $entityType = $context['entity_type'] ?? null;
            $entityId = $context['entity_id'] ?? null;
            $userId = $this->getEntityAssignedId($entityType, $entityId);
            return $userId ? [User::find($userId)] : [];
        }

        // Array of user IDs
        if (is_array($usersParam)) {
            return User::whereIn('id', $usersParam)->where('status', 'active')->get()->all();
        }

        // Single user ID
        if (is_numeric($usersParam)) {
            $user = User::find($usersParam);
            return $user ? [$user] : [];
        }

        return [];
    }

    /**
     * Get default recipients (entity creator or assigned user)
     */
    protected function getDefaultRecipients(array $context): array
    {
        $entityType = $context['entity_type'] ?? null;
        $entityId = $context['entity_id'] ?? null;

        // Try assigned user first
        $assignedId = $this->getEntityAssignedId($entityType, $entityId);
        if ($assignedId) {
            $user = User::find($assignedId);
            if ($user) {
                return [$user];
            }
        }

        // Fallback to creator
        $creatorId = $this->getEntityCreatorId($entityType, $entityId);
        if ($creatorId) {
            $user = User::find($creatorId);
            if ($user) {
                return [$user];
            }
        }

        return [];
    }

    /**
     * Get entity creator user ID
     */
    protected function getEntityCreatorId(?string $entityType, ?int $entityId): ?int
    {
        if (!$entityType || !$entityId) {
            return null;
        }

        return match ($entityType) {
            'ticket' => Ticket::find($entityId)?->created_by,
            'document' => Document::find($entityId)?->created_by,
            default => null,
        };
    }

    /**
     * Get entity assigned user ID
     */
    protected function getEntityAssignedId(?string $entityType, ?int $entityId): ?int
    {
        if (!$entityType || !$entityId) {
            return null;
        }

        return match ($entityType) {
            'ticket' => Ticket::find($entityId)?->assigned_to,
            default => null,
        };
    }

    /**
     * Action: Update field
     */
    protected function actionUpdateField(array $params, array $context): array
    {
        $field = $params['field'] ?? null;
        $value = $params['value'] ?? null;
        $entityType = $context['entity_type'] ?? null;
        $entityId = $context['entity_id'] ?? null;

        if (!$field || !$entityType || !$entityId) {
            return ['success' => false, 'message' => 'Missing required parameters'];
        }

        if ($entityType === 'ticket') {
            $ticket = \App\Models\Ticket::find($entityId);
            if ($ticket && isset($ticket->$field)) {
                $ticket->$field = $value;
                $ticket->save();
                return ['success' => true, 'message' => "Updated field {$field} for ticket {$entityId}"];
            }
        }

        return ['success' => false, 'message' => 'Entity not found or field not supported'];
    }

    /**
     * Action: Create record
     * 
     * Params:
     * - type: string (required) - Record type: 'ticket', 'grade', 'attendance', 'document', 'risk'
     * - data: array (required) - Record data (varies by type)
     * - tenant_id: int (optional) - Override tenant_id from context
     */
    protected function actionCreateRecord(array $params, array $context): array
    {
        $type = $params['type'] ?? null;
        $data = $params['data'] ?? [];
        $tenantId = $params['tenant_id'] ?? $context['tenant_id'] ?? null;

        if (!$type) {
            return ['success' => false, 'message' => 'Missing record type'];
        }

        try {
            $result = match ($type) {
                'ticket' => $this->createTicket($data, $tenantId, $context),
                'grade' => $this->createGrade($data, $tenantId, $context),
                'attendance' => $this->createAttendance($data, $tenantId, $context),
                'document' => $this->createDocument($data, $tenantId, $context),
                'risk' => $this->createRisk($data, $tenantId, $context),
                default => ['success' => false, 'message' => "Unsupported record type: {$type}"],
            };

            return $result;
        } catch (\Exception $e) {
            Log::error("Failed to create record", [
                'type' => $type,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => "Failed to create {$type}: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Create a ticket record
     */
    protected function createTicket(array $data, ?int $tenantId, array $context): array
    {
        $required = ['title', 'description'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        $ticket = Ticket::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'status' => $data['status'] ?? 'open',
            'priority' => $data['priority'] ?? 'medium',
            'category' => $data['category'] ?? 'general',
            'created_by' => $data['created_by'] ?? $context['user_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'due_at' => isset($data['due_at']) ? \Carbon\Carbon::parse($data['due_at']) : null,
            'tenant_id' => $tenantId,
        ]);

        return [
            'success' => true,
            'message' => "Ticket created: {$ticket->id}",
            'data' => ['ticket_id' => $ticket->id],
        ];
    }

    /**
     * Create a grade record
     */
    protected function createGrade(array $data, ?int $tenantId, array $context): array
    {
        $required = ['student_user_id', 'lesson_id', 'value'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        // Check if grade already exists
        $existing = DB::table('grades')
            ->where('student_user_id', $data['student_user_id'])
            ->where('lesson_id', $data['lesson_id'])
            ->first();

        if ($existing) {
            // Update existing grade
            DB::table('grades')
                ->where('id', $existing->id)
                ->update([
                    'value' => $data['value'],
                    'weight' => $data['weight'] ?? $existing->weight ?? 1,
                    'grade_type' => $data['grade_type'] ?? $existing->grade_type,
                    'comment' => $data['comment'] ?? $existing->comment,
                    'updated_at' => now(),
                ]);

            return [
                'success' => true,
                'message' => "Grade updated: {$existing->id}",
                'data' => ['grade_id' => $existing->id],
            ];
        }

        // Create new grade
        $gradeId = DB::table('grades')->insertGetId([
            'student_user_id' => $data['student_user_id'],
            'lesson_id' => $data['lesson_id'],
            'assignment_id' => $data['assignment_id'] ?? null,
            'value' => $data['value'],
            'weight' => $data['weight'] ?? 1,
            'grade_type' => $data['grade_type'] ?? null,
            'comment' => $data['comment'] ?? null,
            'created_by' => $data['created_by'] ?? $context['user_id'] ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Grade created: {$gradeId}",
            'data' => ['grade_id' => $gradeId],
        ];
    }

    /**
     * Create an attendance record
     */
    protected function createAttendance(array $data, ?int $tenantId, array $context): array
    {
        $required = ['student_user_id', 'lesson_id', 'status'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        // Check if attendance already exists
        $existing = DB::table('attendance')
            ->where('student_user_id', $data['student_user_id'])
            ->where('lesson_id', $data['lesson_id'])
            ->first();

        if ($existing) {
            // Update existing attendance
            DB::table('attendance')
                ->where('id', $existing->id)
                ->update([
                    'status' => $data['status'],
                    'comment' => $data['comment'] ?? $existing->comment,
                    'updated_at' => now(),
                ]);

            return [
                'success' => true,
                'message' => "Attendance updated: {$existing->id}",
                'data' => ['attendance_id' => $existing->id],
            ];
        }

        // Create new attendance
        $attendanceId = DB::table('attendance')->insertGetId([
            'student_user_id' => $data['student_user_id'],
            'lesson_id' => $data['lesson_id'],
            'status' => $data['status'],
            'reason' => $data['comment'] ?? $data['reason'] ?? null,
            'created_by' => $data['created_by'] ?? $context['user_id'] ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Attendance created: {$attendanceId}",
            'data' => ['attendance_id' => $attendanceId],
        ];
    }

    /**
     * Create a document record
     */
    protected function createDocument(array $data, ?int $tenantId, array $context): array
    {
        $required = ['type', 'template_id'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        $document = Document::create([
            'type' => $data['type'],
            'template_id' => $data['template_id'],
            'number' => $data['number'] ?? null,
            'date' => isset($data['date']) ? \Carbon\Carbon::parse($data['date']) : now(),
            'status' => $data['status'] ?? 'draft',
            'data_json' => $data['data_json'] ?? [],
            'created_by' => $data['created_by'] ?? $context['user_id'] ?? null,
            'signed_by' => $data['signed_by'] ?? null,
            'tenant_id' => $tenantId,
        ]);

        return [
            'success' => true,
            'message' => "Document created: {$document->id}",
            'data' => ['document_id' => $document->id],
        ];
    }

    /**
     * Create a risk record
     */
    protected function createRisk(array $data, ?int $tenantId, array $context): array
    {
        $required = ['entity_type', 'entity_id', 'risk_level', 'risk_score'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        $risk = \App\Models\Risk::create([
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'risk_level' => $data['risk_level'],
            'risk_score' => $data['risk_score'],
            'factors' => $data['factors'] ?? [],
            'metadata' => $data['metadata'] ?? [],
            'term_id' => $data['term_id'] ?? null,
            'calculated_by' => $data['calculated_by'] ?? $context['user_id'] ?? null,
            'calculated_at' => now(),
            'tenant_id' => $tenantId,
        ]);

        return [
            'success' => true,
            'message' => "Risk created: {$risk->id}",
            'data' => ['risk_id' => $risk->id],
        ];
    }

    /**
     * Action: Log event
     */
    protected function actionLogEvent(array $params, array $context): array
    {
        $message = $params['message'] ?? 'Rule action executed';
        Log::info($message, [
            'context' => $context,
            'params' => $params,
        ]);

        return ['success' => true, 'message' => 'Event logged'];
    }

    /**
     * Validate rule conditions syntax
     */
    public function validateRuleConditions(array $conditions): array
    {
        $errors = [];

        foreach ($conditions as $index => $condition) {
            if (!isset($condition['field'])) {
                $errors[] = "Condition #{$index}: missing 'field'";
            }
            if (!isset($condition['operator'])) {
                $errors[] = "Condition #{$index}: missing 'operator'";
            }
            if (!isset($condition['value'])) {
                $errors[] = "Condition #{$index}: missing 'value'";
            }

            $validOperators = [
                'equals', 'not_equals', 'greater_than', 'less_than',
                'greater_or_equal', 'less_or_equal', 'contains', 'not_contains',
                'in', 'not_in', 'exists', 'not_exists',
            ];

            if (isset($condition['operator']) && !in_array($condition['operator'], $validOperators)) {
                $errors[] = "Condition #{$index}: invalid operator '{$condition['operator']}'";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate rule actions syntax
     */
    public function validateRuleActions(array $actions): array
    {
        $errors = [];

        foreach ($actions as $index => $action) {
            if (!isset($action['type'])) {
                $errors[] = "Action #{$index}: missing 'type'";
            }

            $validTypes = [
                'assign_user', 'change_status', 'send_notification', 'notification',
                'update_field', 'create_record', 'log_event',
            ];

            if (isset($action['type']) && !in_array($action['type'], $validTypes)) {
                $errors[] = "Action #{$index}: invalid type '{$action['type']}'";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
