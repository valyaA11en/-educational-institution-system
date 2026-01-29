<?php

namespace App\Services\Rule;

use App\Models\Rule;
use App\Models\OutboxEvent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RuleEngine
{
    /**
     * Process an event through all enabled rules
     */
    public function processEvent(OutboxEvent $event): void
    {
        $rules = Rule::where('enabled', true)
            ->where(function ($q) use ($event) {
                $q->where('scope', 'global')
                    ->orWhere(function ($q) use ($event) {
                        // TODO: scope filtering by org/term based on event context
                    });
            })
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            try {
                if ($this->matchesConditions($rule, $event)) {
                    $this->executeActions($rule, $event);
                }
            } catch (\Exception $e) {
                Log::error("Rule execution failed", [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process rules for a generic context (not just OutboxEvent)
     */
    public function processContext(array $context, string $scope = 'global'): void
    {
        $rules = Rule::where('enabled', true)
            ->where('scope', $scope)
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            try {
                if ($this->matchesConditionsForContext($rule, $context)) {
                    $this->executeActionsForContext($rule, $context);
                }
            } catch (\Exception $e) {
                Log::error("Rule execution failed for context", [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Validate rule conditions and actions structure
     */
    public function validateRule(array $conditions, array $actions): array
    {
        $errors = [];

        // Validate conditions structure
        if (!is_array($conditions)) {
            $errors[] = 'Conditions must be an array';
        } else {
            $errors = array_merge($errors, $this->validateConditions($conditions));
        }

        // Validate actions structure
        if (!is_array($actions)) {
            $errors[] = 'Actions must be an array';
        } else {
            $errors = array_merge($errors, $this->validateActions($actions));
        }

        return $errors;
    }

    /**
     * Check if rule conditions match the event (public for testing)
     */
    public function matchesConditions(Rule $rule, OutboxEvent $event): bool
    {
        $conditions = $rule->conditions_json ?? [];

        if (empty($conditions)) {
            return false;
        }

        return $this->evaluateConditions($conditions, $event);
    }

    /**
     * Check if rule conditions match the context
     */
    private function matchesConditionsForContext(Rule $rule, array $context): bool
    {
        $conditions = $rule->conditions_json ?? [];

        if (empty($conditions)) {
            return false;
        }

        return $this->evaluateConditionsForContext($conditions, $context);
    }

    /**
     * Evaluate conditions with support for AND/OR logic and nested conditions
     */
    private function evaluateConditions(array $conditions, OutboxEvent $event): bool
    {
        // If it's a single condition (not wrapped in logic)
        if (isset($conditions['field']) || isset($conditions['operator'])) {
            return $this->evaluateCondition($conditions, $event);
        }

        // If it's a logic group (AND/OR)
        if (isset($conditions['logic'])) {
            $logic = $conditions['logic'];
            $subConditions = $conditions['conditions'] ?? [];

            if ($logic === 'AND') {
                foreach ($subConditions as $condition) {
                    if (!$this->evaluateConditions($condition, $event)) {
                        return false;
                    }
                }
                return true;
            } elseif ($logic === 'OR') {
                foreach ($subConditions as $condition) {
                    if ($this->evaluateConditions($condition, $event)) {
                        return true;
                    }
                }
                return false;
            }
        }

        // If it's an array of conditions, default to AND
        if (is_array($conditions) && !isset($conditions['logic'])) {
            foreach ($conditions as $condition) {
                if (!$this->evaluateConditions($condition, $event)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Evaluate conditions for generic context
     */
    private function evaluateConditionsForContext(array $conditions, array $context): bool
    {
        if (isset($conditions['field']) || isset($conditions['operator'])) {
            return $this->evaluateConditionForContext($conditions, $context);
        }

        if (isset($conditions['logic'])) {
            $logic = $conditions['logic'];
            $subConditions = $conditions['conditions'] ?? [];

            if ($logic === 'AND') {
                foreach ($subConditions as $condition) {
                    if (!$this->evaluateConditionsForContext($condition, $context)) {
                        return false;
                    }
                }
                return true;
            } elseif ($logic === 'OR') {
                foreach ($subConditions as $condition) {
                    if ($this->evaluateConditionsForContext($condition, $context)) {
                        return true;
                    }
                }
                return false;
            }
        }

        if (is_array($conditions) && !isset($conditions['logic'])) {
            foreach ($conditions as $condition) {
                if (!$this->evaluateConditionsForContext($condition, $context)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Evaluate a single condition
     */
    private function evaluateCondition(array $condition, OutboxEvent $event): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return false;
        }

        $eventValue = $this->getEventValue($event, $field);

        return $this->compareValues($eventValue, $operator, $value);
    }

    /**
     * Evaluate a single condition for context
     */
    private function evaluateConditionForContext(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return false;
        }

        $contextValue = data_get($context, $field);

        return $this->compareValues($contextValue, $operator, $value);
    }

    /**
     * Compare values based on operator
     */
    private function compareValues(mixed $actualValue, string $operator, mixed $expectedValue): bool
    {
        return match ($operator) {
            'equals' => $actualValue == $expectedValue,
            'not_equals' => $actualValue != $expectedValue,
            'contains' => str_contains((string) $actualValue, (string) $expectedValue),
            'not_contains' => !str_contains((string) $actualValue, (string) $expectedValue),
            'starts_with' => str_starts_with((string) $actualValue, (string) $expectedValue),
            'ends_with' => str_ends_with((string) $actualValue, (string) $expectedValue),
            'greater_than' => $actualValue > $expectedValue,
            'greater_than_or_equal' => $actualValue >= $expectedValue,
            'less_than' => $actualValue < $expectedValue,
            'less_than_or_equal' => $actualValue <= $expectedValue,
            'in' => in_array($actualValue, (array) $expectedValue, true),
            'not_in' => !in_array($actualValue, (array) $expectedValue, true),
            'is_null' => is_null($actualValue),
            'is_not_null' => !is_null($actualValue),
            'is_empty' => empty($actualValue),
            'is_not_empty' => !empty($actualValue),
            'regex' => preg_match($expectedValue, (string) $actualValue) === 1,
            default => false,
        };
    }

    /**
     * Get value from event by field path
     */
    private function getEventValue(OutboxEvent $event, string $field): mixed
    {
        if (str_starts_with($field, 'payload.')) {
            $payloadField = substr($field, 8);
            return data_get($event->payload_json, $payloadField);
        }

        if (str_starts_with($field, 'entity.')) {
            // Load entity if needed
            $entityField = substr($field, 7);
            if ($event->entity_type && $event->entity_id) {
                $entity = $this->loadEntity($event->entity_type, $event->entity_id);
                return data_get($entity, $entityField);
            }
            return null;
        }

        return $event->{$field} ?? null;
    }

    /**
     * Load entity by type and id
     */
    private function loadEntity(string $entityType, int $entityId): ?Model
    {
        $modelClass = match ($entityType) {
            'ticket' => Ticket::class,
            'user' => User::class,
            default => null,
        };

        if (!$modelClass) {
            return null;
        }

        return $modelClass::find($entityId);
    }

    /**
     * Execute all actions for a rule
     */
    private function executeActions(Rule $rule, OutboxEvent $event): void
    {
        $actions = $rule->actions_json ?? [];

        foreach ($actions as $action) {
            try {
                $this->executeAction($action, $event, $rule);
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
     * Execute all actions for context
     */
    private function executeActionsForContext(Rule $rule, array $context): void
    {
        $actions = $rule->actions_json ?? [];

        foreach ($actions as $action) {
            try {
                $this->executeActionForContext($action, $context, $rule);
            } catch (\Exception $e) {
                Log::error("Action execution failed for context", [
                    'rule_id' => $rule->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Execute a single action
     */
    private function executeAction(array $action, OutboxEvent $event, Rule $rule): void
    {
        $type = $action['type'] ?? null;

        match ($type) {
            'send_notification' => $this->sendNotification($action, $event),
            'create_ticket' => $this->createTicket($action, $event),
            'update_status' => $this->updateStatus($action, $event),
            'assign_role' => $this->assignRole($action, $event),
            'assign_user' => $this->assignUser($action, $event),
            'update_priority' => $this->updatePriority($action, $event),
            'log_event' => $this->logEvent($action, $event, $rule),
            'send_email' => $this->sendEmail($action, $event),
            'delay' => $this->delay($action, $event, $rule),
            default => Log::warning("Unknown action type: {$type}", ['rule_id' => $rule->id]),
        };
    }

    /**
     * Execute a single action for context
     */
    private function executeActionForContext(array $action, array $context, Rule $rule): void
    {
        $type = $action['type'] ?? null;

        match ($type) {
            'send_notification' => $this->sendNotificationForContext($action, $context),
            'create_ticket' => $this->createTicketForContext($action, $context),
            'update_status' => $this->updateStatusForContext($action, $context),
            'assign_role' => $this->assignRoleForContext($action, $context),
            'log_event' => $this->logEventForContext($action, $context, $rule),
            default => Log::warning("Unknown action type: {$type}", ['rule_id' => $rule->id]),
        };
    }

    /**
     * Send notification action
     */
    private function sendNotification(array $action, OutboxEvent $event): void
    {
        $userId = $this->resolveUserId($action, $event);
        if (!$userId) {
            return;
        }

        \App\Models\Notification::create([
            'user_id' => $userId,
            'type' => $action['notification_type'] ?? 'rule.triggered',
            'payload_json' => array_merge(
                [
                    'message' => $action['message'] ?? 'Rule triggered',
                    'event' => $event->event_type,
                    'rule_id' => $action['rule_id'] ?? null,
                ],
                $action['payload'] ?? []
            ),
            'channel' => $action['channel'] ?? 'in_app',
        ]);
    }

    /**
     * Send notification for context
     */
    private function sendNotificationForContext(array $action, array $context): void
    {
        $userId = $action['user_id'] ?? $context['user_id'] ?? null;
        if (!$userId) {
            return;
        }

        \App\Models\Notification::create([
            'user_id' => $userId,
            'type' => $action['notification_type'] ?? 'rule.triggered',
            'payload_json' => array_merge(
                [
                    'message' => $action['message'] ?? 'Rule triggered',
                ],
                $action['payload'] ?? []
            ),
            'channel' => $action['channel'] ?? 'in_app',
        ]);
    }

    /**
     * Create ticket action
     */
    private function createTicket(array $action, OutboxEvent $event): void
    {
        $createdBy = $this->resolveUserId($action, $event) ?? $event->actor_user_id;
        if (!$createdBy) {
            return;
        }

        Ticket::create([
            'created_by' => $createdBy,
            'category' => $action['category'] ?? 'general',
            'title' => $this->resolveTemplate($action['title'] ?? 'Ticket from rule', $event),
            'description' => $this->resolveTemplate($action['description'] ?? '', $event),
            'status' => $action['status'] ?? 'open',
            'priority' => $action['priority'] ?? 'normal',
            'assigned_to' => $action['assigned_to'] ?? null,
        ]);
    }

    /**
     * Create ticket for context
     */
    private function createTicketForContext(array $action, array $context): void
    {
        $createdBy = $action['created_by'] ?? $context['user_id'] ?? null;
        if (!$createdBy) {
            return;
        }

        Ticket::create([
            'created_by' => $createdBy,
            'category' => $action['category'] ?? 'general',
            'title' => $this->resolveTemplateForContext($action['title'] ?? 'Ticket from rule', $context),
            'description' => $this->resolveTemplateForContext($action['description'] ?? '', $context),
            'status' => $action['status'] ?? 'open',
            'priority' => $action['priority'] ?? 'normal',
            'assigned_to' => $action['assigned_to'] ?? null,
        ]);
    }

    /**
     * Update status action
     */
    private function updateStatus(array $action, OutboxEvent $event): void
    {
        if (!$event->entity_type || !$event->entity_id) {
            return;
        }

        $entity = $this->loadEntity($event->entity_type, $event->entity_id);
        if (!$entity || !method_exists($entity, 'update')) {
            return;
        }

        $statusField = $action['status_field'] ?? 'status';
        $statusValue = $action['status'] ?? null;

        if ($statusValue) {
            $entity->update([$statusField => $statusValue]);
        }
    }

    /**
     * Update status for context
     */
    private function updateStatusForContext(array $action, array $context): void
    {
        $entityType = $context['entity_type'] ?? null;
        $entityId = $context['entity_id'] ?? null;

        if (!$entityType || !$entityId) {
            return;
        }

        $entity = $this->loadEntity($entityType, $entityId);
        if (!$entity || !method_exists($entity, 'update')) {
            return;
        }

        $statusField = $action['status_field'] ?? 'status';
        $statusValue = $action['status'] ?? null;

        if ($statusValue) {
            $entity->update([$statusField => $statusValue]);
        }
    }

    /**
     * Assign role action
     */
    private function assignRole(array $action, OutboxEvent $event): void
    {
        $userId = $this->resolveUserId($action, $event);
        $roleId = $action['role_id'] ?? null;

        if (!$userId || !$roleId) {
            return;
        }

        $user = User::find($userId);
        if ($user) {
            $user->roles()->syncWithoutDetaching([$roleId]);
        }
    }

    /**
     * Assign role for context
     */
    private function assignRoleForContext(array $action, array $context): void
    {
        $userId = $action['user_id'] ?? $context['user_id'] ?? null;
        $roleId = $action['role_id'] ?? null;

        if (!$userId || !$roleId) {
            return;
        }

        $user = User::find($userId);
        if ($user) {
            $user->roles()->syncWithoutDetaching([$roleId]);
        }
    }

    /**
     * Assign user action
     */
    private function assignUser(array $action, OutboxEvent $event): void
    {
        if (!$event->entity_type || !$event->entity_id) {
            return;
        }

        $entity = $this->loadEntity($event->entity_type, $event->entity_id);
        $assigneeId = $action['user_id'] ?? $this->resolveUserId($action, $event);

        if ($entity && $assigneeId && method_exists($entity, 'update')) {
            $assignField = $action['assign_field'] ?? 'assigned_to';
            $entity->update([$assignField => $assigneeId]);
        }
    }

    /**
     * Update priority action
     */
    private function updatePriority(array $action, OutboxEvent $event): void
    {
        if (!$event->entity_type || !$event->entity_id) {
            return;
        }

        $entity = $this->loadEntity($event->entity_type, $event->entity_id);
        $priority = $action['priority'] ?? null;

        if ($entity && $priority && method_exists($entity, 'update')) {
            $priorityField = $action['priority_field'] ?? 'priority';
            $entity->update([$priorityField => $priority]);
        }
    }

    /**
     * Send email action (placeholder - requires mail configuration)
     */
    private function sendEmail(array $action, OutboxEvent $event): void
    {
        $userId = $this->resolveUserId($action, $event);
        if (!$userId) {
            return;
        }

        $user = User::find($userId);
        if (!$user || !$user->email) {
            return;
        }

        // TODO: Implement email sending
        Log::info("Email action triggered", [
            'user_id' => $userId,
            'email' => $user->email,
            'subject' => $action['subject'] ?? 'Notification',
        ]);
    }

    /**
     * Delay action - re-queue the rule execution
     */
    private function delay(array $action, OutboxEvent $event, Rule $rule): void
    {
        $delaySeconds = $action['delay_seconds'] ?? 0;
        if ($delaySeconds <= 0) {
            return;
        }

        // TODO: Implement delayed execution using queue
        Log::info("Delay action triggered", [
            'rule_id' => $rule->id,
            'delay_seconds' => $delaySeconds,
        ]);
    }

    /**
     * Log event action
     */
    private function logEvent(array $action, OutboxEvent $event, Rule $rule): void
    {
        \App\Services\AuditService::log(
            $action['event_type'] ?? 'rule.triggered',
            'rule',
            $rule->id,
            null,
            [
                'event_id' => $event->id,
                'event_type' => $event->event_type,
                'action' => $action,
            ],
            $event->actor_user_id
        );
    }

    /**
     * Log event for context
     */
    private function logEventForContext(array $action, array $context, Rule $rule): void
    {
        \App\Services\AuditService::log(
            $action['event_type'] ?? 'rule.triggered',
            'rule',
            $rule->id,
            null,
            [
                'action' => $action,
                'context' => $context,
            ],
            $context['user_id'] ?? null
        );
    }

    /**
     * Resolve user ID from action or event
     */
    private function resolveUserId(array $action, OutboxEvent $event): ?int
    {
        if (isset($action['user_id'])) {
            return (int) $action['user_id'];
        }

        if (isset($action['user_field'])) {
            return (int) $this->getEventValue($event, $action['user_field']);
        }

        return $event->actor_user_id;
    }

    /**
     * Resolve template string with event values
     */
    private function resolveTemplate(string $template, OutboxEvent $event): string
    {
        $replacements = [
            '{event_type}' => $event->event_type,
            '{actor_id}' => $event->actor_user_id ?? '',
        ];

        foreach ($replacements as $key => $value) {
            $template = str_replace($key, $value, $template);
        }

        return $template;
    }

    /**
     * Resolve template string with context values
     */
    private function resolveTemplateForContext(string $template, array $context): string
    {
        foreach ($context as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }

    /**
     * Validate conditions structure
     */
    private function validateConditions(array $conditions): array
    {
        $errors = [];

        // If it's a logic group
        if (isset($conditions['logic'])) {
            if (!in_array($conditions['logic'], ['AND', 'OR'])) {
                $errors[] = 'Logic must be AND or OR';
            }

            if (!isset($conditions['conditions']) || !is_array($conditions['conditions'])) {
                $errors[] = 'Logic group must have conditions array';
            } else {
                foreach ($conditions['conditions'] as $condition) {
                    $errors = array_merge($errors, $this->validateConditions($condition));
                }
            }
        } else {
            // Single condition
            if (!isset($conditions['field'])) {
                $errors[] = 'Condition must have field';
            }

            if (!isset($conditions['operator'])) {
                $errors[] = 'Condition must have operator';
            } elseif (!in_array($conditions['operator'], [
                'equals', 'not_equals', 'contains', 'not_contains',
                'starts_with', 'ends_with', 'greater_than', 'greater_than_or_equal',
                'less_than', 'less_than_or_equal', 'in', 'not_in',
                'is_null', 'is_not_null', 'is_empty', 'is_not_empty', 'regex'
            ])) {
                $errors[] = 'Invalid operator: ' . $conditions['operator'];
            }
        }

        return $errors;
    }

    /**
     * Validate actions structure
     */
    private function validateActions(array $actions): array
    {
        $errors = [];
        $validActionTypes = [
            'send_notification', 'create_ticket', 'update_status',
            'assign_role', 'assign_user', 'update_priority',
            'log_event', 'send_email', 'delay'
        ];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                $errors[] = 'Action must be an array';
                continue;
            }

            if (!isset($action['type'])) {
                $errors[] = 'Action must have type';
                continue;
            }

            if (!in_array($action['type'], $validActionTypes)) {
                $errors[] = 'Invalid action type: ' . $action['type'];
            }
        }

        return $errors;
    }
}

