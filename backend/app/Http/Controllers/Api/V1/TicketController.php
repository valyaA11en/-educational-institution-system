<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\Ticket\SlaService;
use App\Services\Rule\RuleEngine;
use App\Services\Outbox\OutboxService;
use App\Support\Events\EventTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    public function __construct(
        private SlaService $slaService,
        private RuleEngine $ruleEngine,
        private OutboxService $outboxService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $query = Ticket::with(['creator', 'assignee']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        if ($assignedTo = $request->query('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        if ($createdBy = $request->query('created_by')) {
            $query->where('created_by', $createdBy);
        }

        if ($request->boolean('overdue')) {
            $query->where('status', '!=', 'closed')
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now());
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return response()->json($tickets);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category' => ['required', 'string'],
            'priority' => ['required', 'in:low,normal,medium,high,critical'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['integer', 'exists:files,id'],
        ]);

        DB::beginTransaction();
        try {
            $now = now();
            $slaHours = 72; // Default SLA hours
            
            // Calculate first_response_due_at = now + min(24h, sla_hours/3)
            $firstResponseHours = min(24, $slaHours / 3);
            $firstResponseDueAt = $now->copy()->addHours($firstResponseHours);
            
            // Calculate resolution_due_at = now + sla_hours
            $resolutionDueAt = $now->copy()->addHours($slaHours);

            $ticket = Ticket::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'category' => $validated['category'],
                'priority' => $validated['priority'],
                'status' => 'open',
                'created_by' => auth()->id(),
                'assigned_to' => $validated['assigned_to'] ?? null,
                'sla_hours' => $slaHours,
                'first_response_due_at' => $firstResponseDueAt,
                'resolution_due_at' => $resolutionDueAt,
                'is_overdue' => false,
            ]);

            // Apply SLA configuration (for backward compatibility with existing SLA fields)
            $this->slaService->applySlaToTicket($ticket);
            $ticket->refresh();

            // Create outbox event for rule engine
            $event = $this->outboxService->record(
                EventTypes::TICKET_CREATED,
                auth()->id(),
                'ticket',
                $ticket->id,
                [
                    'title' => $ticket->title,
                    'category' => $ticket->category,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'assigned_to' => $ticket->assigned_to,
                    'sla_level' => $ticket->sla_level,
                    'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
                ]
            );

            // Process rules
            $this->ruleEngine->processEvent($event);

            DB::commit();
            return response()->json($ticket->load(['creator', 'assignee']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create ticket', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function show(int $id): JsonResponse
    {
        $ticket = Ticket::with(['creator', 'assignee', 'messages.user'])->findOrFail($id);
        $this->authorize('view', $ticket);
        return response()->json($ticket);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorize('manage', $ticket);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status' => ['sometimes', 'in:open,in_progress,resolved,closed'],
            'priority' => ['sometimes', 'in:low,normal,medium,high,critical'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $oldStatus = $ticket->status;
        $oldPriority = $ticket->priority;
        $oldAssignedTo = $ticket->assigned_to;

        DB::beginTransaction();
        try {
            // Handle status change
            if (isset($validated['status'])) {
                // Set first_response_at when status changes to in_progress (if not already set)
                if ($validated['status'] === 'in_progress' && !$ticket->first_response_at) {
                    $validated['first_response_at'] = now();
                }
                
                // Set resolved_at when status changes to resolved or closed (if not already set)
                if (in_array($validated['status'], ['resolved', 'closed']) && !$ticket->resolved_at) {
                    $validated['resolved_at'] = now();
                }
                
                if ($validated['status'] === 'closed' && !$ticket->closed_at) {
                    $validated['closed_at'] = now();
                    $this->slaService->markResolution($ticket);
                }
            }

            // Handle priority change - reapply SLA
            if (isset($validated['priority']) && $validated['priority'] !== $ticket->priority) {
                $ticket->priority = $validated['priority'];
                $this->slaService->applySlaToTicket($ticket);
                unset($validated['priority']); // Already applied
            }

            // Handle assignment change
            if (isset($validated['assigned_to']) && $validated['assigned_to'] !== $ticket->assigned_to) {
                // Will be handled by event
            }

            $ticket->update($validated);
            $ticket->refresh();

            // Create events for rule engine
            $events = [];

            // Status changed event
            if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
                $events[] = $this->outboxService->record(
                    EventTypes::TICKET_STATUS_CHANGED,
                    auth()->id(),
                    'ticket',
                    $ticket->id,
                    [
                        'old_status' => $oldStatus,
                        'new_status' => $validated['status'],
                        'ticket' => [
                            'id' => $ticket->id,
                            'title' => $ticket->title,
                            'priority' => $ticket->priority,
                        ],
                    ]
                );

                if ($validated['status'] === 'closed') {
                    $events[] = $this->outboxService->record(
                        EventTypes::TICKET_RESOLVED,
                        auth()->id(),
                        'ticket',
                        $ticket->id,
                        [
                            'ticket' => [
                                'id' => $ticket->id,
                                'title' => $ticket->title,
                                'sla_resolved_at' => $ticket->sla_resolved_at?->toIso8601String(),
                                'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
                            ],
                        ]
                    );
                }
            }

            // Priority changed event
            if (isset($validated['priority']) && $validated['priority'] !== $oldPriority) {
                $events[] = $this->outboxService->record(
                    EventTypes::TICKET_PRIORITY_CHANGED,
                    auth()->id(),
                    'ticket',
                    $ticket->id,
                    [
                        'old_priority' => $oldPriority,
                        'new_priority' => $validated['priority'],
                        'ticket' => [
                            'id' => $ticket->id,
                            'title' => $ticket->title,
                        ],
                    ]
                );
            }

            // Assignment changed event
            if (isset($validated['assigned_to']) && $validated['assigned_to'] != $oldAssignedTo) {
                $events[] = $this->outboxService->record(
                    EventTypes::TICKET_ASSIGNED,
                    auth()->id(),
                    'ticket',
                    $ticket->id,
                    [
                        'old_assigned_to' => $oldAssignedTo,
                        'new_assigned_to' => $validated['assigned_to'],
                        'ticket' => [
                            'id' => $ticket->id,
                            'title' => $ticket->title,
                        ],
                    ]
                );
            }

            // General update event
            if (empty($events)) {
                $events[] = $this->outboxService->record(
                    EventTypes::TICKET_UPDATED,
                    auth()->id(),
                    'ticket',
                    $ticket->id,
                    [
                        'ticket' => [
                            'id' => $ticket->id,
                            'title' => $ticket->title,
                            'status' => $ticket->status,
                            'priority' => $ticket->priority,
                        ],
                    ]
                );
            }

            // Process rules for all events
            foreach ($events as $event) {
                $this->ruleEngine->processEvent($event);
            }

            DB::commit();
            return response()->json($ticket->load(['creator', 'assignee']));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update ticket', ['ticket_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function messages(int $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        $messages = $ticket->messages()->with('user')->orderBy('created_at')->get();

        return response()->json($messages);
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorize('view', $ticket);

        $validated = $request->validate([
            'text' => ['required', 'string'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        DB::beginTransaction();
        try {
            $isFirstMessage = $ticket->messages()->count() === 0;
            $isFirstResponse = !$ticket->sla_first_response_at && 
                               auth()->id() !== $ticket->created_by;

            $message = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'text' => $validated['text'],
                'is_internal' => $validated['is_internal'] ?? false,
            ]);

            // Mark first response if needed
            if ($isFirstResponse) {
                $this->slaService->markFirstResponse($ticket);
                $ticket->refresh();

                // Create first response event
                $event = $this->outboxService->record(
                    EventTypes::TICKET_FIRST_RESPONSE,
                    auth()->id(),
                    'ticket',
                    $ticket->id,
                    [
                        'ticket' => [
                            'id' => $ticket->id,
                            'title' => $ticket->title,
                            'sla_first_response_at' => $ticket->sla_first_response_at?->toIso8601String(),
                            'sla_response_due_at' => $ticket->sla_response_due_at?->toIso8601String(),
                        ],
                    ]
                );

                $this->ruleEngine->processEvent($event);
            }

            // Create message created event
            $event = $this->outboxService->record(
                EventTypes::TICKET_MESSAGE_CREATED,
                auth()->id(),
                'ticket',
                $ticket->id,
                [
                    'message_id' => $message->id,
                    'ticket' => [
                        'id' => $ticket->id,
                        'title' => $ticket->title,
                    ],
                    'is_first_message' => $isFirstMessage,
                    'is_first_response' => $isFirstResponse,
                ]
            );

            $this->ruleEngine->processEvent($event);

            DB::commit();
            return response()->json($message->load('user'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send ticket message', ['ticket_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function slaReport(Request $request): JsonResponse
    {
        $this->authorize('manage', Ticket::class);

        $from = $request->query('from') ? \Carbon\Carbon::parse($request->query('from')) : null;
        $to = $request->query('to') ? \Carbon\Carbon::parse($request->query('to')) : null;

        $report = $this->slaService->getSlaReport($from, $to);

        return response()->json($report);
    }

    /**
     * Get SLA report for a specific ticket
     */
    public function ticketSlaReport(int $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorize('view', $ticket);

        $report = $this->slaService->getTicketSlaReport($ticket);

        return response()->json($report);
    }

    /**
     * Get tickets with SLA compliance status
     */
    public function slaCompliance(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $query = Ticket::with(['creator', 'assignee']);

        if ($request->boolean('overdue_only')) {
            $query->where('status', '!=', 'closed')
                ->where(function ($q) {
                    $q->where(function ($q) {
                        $q->whereNotNull('sla_due_at')
                            ->where('sla_due_at', '<', now());
                    })->orWhere(function ($q) {
                        $q->whereNull('sla_first_response_at')
                            ->whereNotNull('sla_response_due_at')
                            ->where('sla_response_due_at', '<', now());
                    });
                });
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        // Add SLA compliance data
        $tickets->getCollection()->transform(function ($ticket) {
            $compliance = $ticket->getSlaCompliance();
            return array_merge($ticket->toArray(), [
                'sla_compliance' => $compliance,
            ]);
        });

        return response()->json($tickets);
    }

    /**
     * Assign ticket to user
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorize('manage', $ticket);

        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $oldAssignedTo = $ticket->assigned_to;

        DB::beginTransaction();
        try {
            $ticket->update(['assigned_to' => $validated['assigned_to']]);

            // Create assignment event
            $event = $this->outboxService->record(
                EventTypes::TICKET_ASSIGNED,
                auth()->id(),
                'ticket',
                $ticket->id,
                [
                    'old_assigned_to' => $oldAssignedTo,
                    'new_assigned_to' => $validated['assigned_to'],
                    'ticket' => [
                        'id' => $ticket->id,
                        'title' => $ticket->title,
                        'priority' => $ticket->priority,
                    ],
                ]
            );

            $this->ruleEngine->processEvent($event);

            DB::commit();
            return response()->json($ticket->load(['creator', 'assignee']));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign ticket', ['ticket_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Change ticket priority
     */
    public function changePriority(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorize('manage', $ticket);

        $validated = $request->validate([
            'priority' => ['required', 'in:low,normal,medium,high,critical'],
        ]);

        $oldPriority = $ticket->priority;

        DB::beginTransaction();
        try {
            $ticket->priority = $validated['priority'];
            $this->slaService->applySlaToTicket($ticket);
            $ticket->refresh();

            // Create priority changed event
            $event = $this->outboxService->record(
                EventTypes::TICKET_PRIORITY_CHANGED,
                auth()->id(),
                'ticket',
                $ticket->id,
                [
                    'old_priority' => $oldPriority,
                    'new_priority' => $validated['priority'],
                    'ticket' => [
                        'id' => $ticket->id,
                        'title' => $ticket->title,
                        'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
                    ],
                ]
            );

            $this->ruleEngine->processEvent($event);

            DB::commit();
            return response()->json($ticket->load(['creator', 'assignee']));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to change ticket priority', ['ticket_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
