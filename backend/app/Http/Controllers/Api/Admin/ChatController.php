<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    public function reports(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $tenantId = (int) $user->tenant_id;
        $perPage = max(1, min(100, (int) ($request->query('per_page') ?? 20)));
        $page = max(1, (int) ($request->query('page') ?? 1));

        $q = DB::table('chat_reports as r')
            ->join('chat_threads as t', 't.id', '=', 'r.thread_id')
            ->leftJoin('chat_messages as m', 'm.id', '=', 'r.message_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.reported_by')
            ->when(Schema::hasColumn('chat_threads', 'tenant_id'), fn ($q) => $q->where('t.tenant_id', $tenantId))
            ->select(
                'r.id',
                'r.thread_id',
                'r.message_id',
                'r.reported_by',
                'r.reason',
                'r.status',
                'r.created_at',
                'r.updated_at',
                'm.text as message_text',
                'u.fio as reporter_fio'
            )
            ->orderByDesc('r.created_at');

        if ($request->filled('status')) {
            $q->where('r.status', $request->status);
        }
        if ($request->filled('threadId')) {
            $q->where('r.thread_id', (int) $request->input('threadId'));
        }

        $total = (clone $q)->count();
        $items = $q->offset(($page - 1) * $perPage)->limit($perPage)->get();

        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return response()->json([
            'data' => $items,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ]);
    }

    public function updateReport(Request $request, $id): JsonResponse
    {
        $v = $request->validate([
            'status' => ['required', Rule::in('open', 'reviewed', 'closed')],
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $tenantId = (int) $user->tenant_id;
        $id = (int) $id;
        $r = DB::table('chat_reports as r')
            ->join('chat_threads as t', 't.id', '=', 'r.thread_id')
            ->when(Schema::hasColumn('chat_threads', 'tenant_id'), fn ($q) => $q->where('t.tenant_id', $tenantId))
            ->where('r.id', $id)
            ->select('r.*')
            ->first();

        if (!$r) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        DB::table('chat_reports')->where('id', $id)->update([
            'status' => $v['status'],
            'updated_at' => now(),
        ]);

        $row = DB::table('chat_reports')->where('id', $id)->first();
        return response()->json($row);
    }
}
