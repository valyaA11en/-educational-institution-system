<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketsController extends Controller
{
    /**
     * Get list of overdue tickets
     */
    public function overdue(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $query = Ticket::with(['creator', 'assignee'])
            ->where('is_overdue', true)
            ->whereIn('status', ['open', 'in_progress']);

        // Filters
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

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        // Filter by overdue hours
        if ($request->has('overdue_hours_min')) {
            $hours = (int) $request->query('overdue_hours_min');
            $query->whereRaw('EXTRACT(EPOCH FROM (NOW() - resolution_due_at)) / 3600 >= ?', [$hours]);
        }

        if ($request->has('overdue_hours_max')) {
            $hours = (int) $request->query('overdue_hours_max');
            $query->whereRaw('EXTRACT(EPOCH FROM (NOW() - resolution_due_at)) / 3600 <= ?', [$hours]);
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'resolution_due_at');
        $sortOrder = $request->query('sort_order', 'asc');
        
        if (in_array($sortBy, ['resolution_due_at', 'created_at', 'priority', 'status'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('resolution_due_at', 'asc');
        }

        $tickets = $query->paginate($request->integer('per_page', 50));

        // Add overdue hours to each ticket
        $tickets->getCollection()->transform(function ($ticket) {
            $overdueMinutes = now()->diffInMinutes($ticket->resolution_due_at);
            $overdueHours = round($overdueMinutes / 60, 2);
            
            return [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_by' => $ticket->created_by,
                'assigned_to' => $ticket->assigned_to,
                'sla_hours' => $ticket->sla_hours,
                'first_response_due_at' => $ticket->first_response_due_at?->toIso8601String(),
                'resolution_due_at' => $ticket->resolution_due_at?->toIso8601String(),
                'first_response_at' => $ticket->first_response_at?->toIso8601String(),
                'resolved_at' => $ticket->resolved_at?->toIso8601String(),
                'is_overdue' => $ticket->is_overdue,
                'overdue_hours' => $overdueHours,
                'overdue_minutes' => $overdueMinutes,
                'created_at' => $ticket->created_at?->toIso8601String(),
                'updated_at' => $ticket->updated_at?->toIso8601String(),
                'creator' => $ticket->creator ? [
                    'id' => $ticket->creator->id,
                    'fio' => $ticket->creator->fio,
                    'email' => $ticket->creator->email,
                ] : null,
                'assignee' => $ticket->assignee ? [
                    'id' => $ticket->assignee->id,
                    'fio' => $ticket->assignee->fio,
                    'email' => $ticket->assignee->email,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $tickets->items(),
            'current_page' => $tickets->currentPage(),
            'per_page' => $tickets->perPage(),
            'total' => $tickets->total(),
            'last_page' => $tickets->lastPage(),
        ]);
    }
}








