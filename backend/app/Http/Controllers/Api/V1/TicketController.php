<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\SLAService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    protected SLAService $slaService;

    public function __construct(SLAService $slaService)
    {
        $this->slaService = $slaService;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $query = Ticket::forTenant($tenantId)->with(['creator', 'assignedUser']);

        // Filters
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }
        if ($request->has('priority')) {
            $query->byPriority($request->priority);
        }
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));
        
        // Add SLA status to each ticket
        $tickets->getCollection()->transform(function ($ticket) {
            $ticket->sla_status = $this->slaService->getSlaStatus($ticket);
            return $ticket;
        });

        return response()->json($tickets);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|string|max:100',
            'priority' => 'required|in:low,normal,high,critical',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket = Ticket::create([
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category,
            'priority' => $request->priority,
            'status' => 'open',
            'created_by' => Auth::id(),
            'tenant_id' => Auth::user()->tenant_id,
        ]);

        // Apply SLA
        $this->slaService->applySla($ticket);
        $ticket->load(['creator', 'assignedUser']);
        $ticket->sla_status = $this->slaService->getSlaStatus($ticket);

        return response()->json(['data' => $ticket], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $ticket = Ticket::forTenant($tenantId)
            ->with(['creator', 'assignedUser', 'messages.user'])
            ->findOrFail($id);
        
        $ticket->sla_status = $this->slaService->getSlaStatus($ticket);
        
        return response()->json(['data' => $ticket]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $ticket = Ticket::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'category' => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket->update($request->only(['title', 'description', 'status', 'category']));
        
        // If status changed to resolved, check SLA
        if ($ticket->wasChanged('status') && $ticket->status === 'resolved') {
            $ticket->resolved_at = now();
            $ticket->save();
            $this->slaService->checkEscalation($ticket);
        }
        
        $ticket->load(['creator', 'assignedUser']);
        $ticket->sla_status = $this->slaService->getSlaStatus($ticket);

        return response()->json(['data' => $ticket]);
    }

    public function assign(Request $request, $id): JsonResponse
    {
        $ticket = Ticket::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'assigned_to' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket->assigned_to = $request->assigned_to;
        $ticket->status = 'in_progress';
        $ticket->save();
        
        $ticket->load(['creator', 'assignedUser']);
        $ticket->sla_status = $this->slaService->getSlaStatus($ticket);

        return response()->json(['data' => $ticket]);
    }

    public function changePriority(Request $request, $id): JsonResponse
    {
        $ticket = Ticket::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'priority' => 'required|in:low,normal,high,critical',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket->priority = $request->priority;
        $ticket->save();
        
        // Reapply SLA with new priority
        $this->slaService->applySla($ticket);
        $ticket->load(['creator', 'assignedUser']);
        $ticket->sla_status = $this->slaService->getSlaStatus($ticket);

        return response()->json(['data' => $ticket]);
    }

    public function ticketSlaReport(Request $request, $id): JsonResponse
    {
        $ticket = Ticket::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $slaStatus = $this->slaService->getSlaStatus($ticket);
        
        return response()->json([
            'ticket_id' => $ticket->id,
            'sla_status' => $slaStatus,
            'created_at' => $ticket->created_at,
            'due_at' => $ticket->due_at,
            'resolved_at' => $ticket->resolved_at,
        ]);
    }

    public function messages(Request $request, $id): JsonResponse
    {
        $ticket = Ticket::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $messages = $ticket->messages()->with('user')->orderBy('created_at', 'asc')->get();
        
        return response()->json(['data' => $messages]);
    }

    public function sendMessage(Request $request, $id): JsonResponse
    {
        $ticket = Ticket::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'is_internal' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
            'is_internal' => $request->is_internal ?? false,
        ]);

        // Check SLA after message (response time)
        $this->slaService->checkEscalation($ticket);

        return response()->json(['data' => $message->load('user')], 201);
    }

    public function slaCompliance(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $tickets = Ticket::forTenant($tenantId)->get();
        
        $stats = [
            'total' => $tickets->count(),
            'with_sla' => 0,
            'ok' => 0,
            'response_breached' => 0,
            'resolution_breached' => 0,
            'no_sla' => 0,
        ];

        foreach ($tickets as $ticket) {
            $slaStatus = $this->slaService->getSlaStatus($ticket);
            
            if ($slaStatus['has_sla']) {
                $stats['with_sla']++;
                $stats[$slaStatus['status']]++;
            } else {
                $stats['no_sla']++;
            }
        }

        return response()->json(['data' => $stats]);
    }

    public function slaReport(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $query = Ticket::forTenant($tenantId);

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        $tickets = $query->get();
        
        $report = [];
        foreach ($tickets as $ticket) {
            $slaStatus = $this->slaService->getSlaStatus($ticket);
            $report[] = [
                'ticket_id' => $ticket->id,
                'title' => $ticket->title,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'created_at' => $ticket->created_at,
                'due_at' => $ticket->due_at,
                'resolved_at' => $ticket->resolved_at,
                'sla_status' => $slaStatus,
            ];
        }

        return response()->json(['data' => $report]);
    }
}