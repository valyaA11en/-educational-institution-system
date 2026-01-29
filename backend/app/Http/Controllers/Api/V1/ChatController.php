<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function threads(Request $request): JsonResponse
    {
        $userId = (int) auth()->id();
        $tenantId = (int) auth()->user()->tenant_id;

        $rows = DB::table('chat_threads as t')
            ->join('chat_members as m', 'm.thread_id', '=', 't.id')
            ->where('m.user_id', $userId)
            ->when(Schema::hasColumn('chat_threads', 'tenant_id'), fn ($q) => $q->where('t.tenant_id', $tenantId))
            ->orderByDesc('t.updated_at')
            ->select('t.*')
            ->distinct()
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function deleteThread(Request $request, $threadId): JsonResponse
    {
        $userId = (int) auth()->id();
        if (!$this->isMember((int) $threadId, $userId)) {
            return response()->json(['message' => 'Access denied'], 403);
        }
        $t = DB::table('chat_threads')->where('id', $threadId)->first();
        if (!$t) {
            return response()->json(['message' => 'Thread not found'], 404);
        }
        if ((int) $t->created_by !== $userId) {
            return response()->json(['message' => 'Only creator can delete the thread'], 403);
        }
        DB::table('chat_threads')->where('id', $threadId)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function createThread(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'type' => 'required|in:dm,group,subject,curator_parents',
            'group_id' => 'nullable|exists:groups,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $userId = (int) auth()->id();

        $id = DB::table('chat_threads')->insertGetId([
            'type' => $request->type,
            'group_id' => $request->group_id,
            'subject_id' => $request->subject_id,
            'created_by' => $userId,
            'tenant_id' => $tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('chat_members')->insert([
            'thread_id' => $id,
            'user_id' => $userId,
            'role_in_chat' => 'moderator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberIds = array_unique(array_merge($request->member_ids ?? [], []));
        foreach ($memberIds as $uid) {
            if ((int) $uid === $userId) {
                continue;
            }
            DB::table('chat_members')->insertOrIgnore([
                'thread_id' => $id,
                'user_id' => $uid,
                'role_in_chat' => 'member',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $row = DB::table('chat_threads')->where('id', $id)->first();
        return response()->json(['data' => $row], 201);
    }

    public function messages(Request $request, $threadId): JsonResponse
    {
        $userId = (int) auth()->id();
        if (!$this->isMember($threadId, $userId)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $perPage = max(1, min(100, (int) ($request->query('per_page') ?? 50)));
        $q = DB::table('chat_messages')
            ->where('thread_id', $threadId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at');

        $total = (clone $q)->count();
        $rows = $q->offset(0)->limit($perPage)->get();

        return response()->json([
            'data' => $rows->reverse()->values()->all(),
            'meta' => ['total' => $total, 'per_page' => $perPage],
        ]);
    }

    public function sendMessage(Request $request, $threadId): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'text' => 'required|string|max:65535',
            'attachments' => 'nullable|array',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $userId = (int) auth()->id();
        if (!$this->isMember($threadId, $userId)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $id = DB::table('chat_messages')->insertGetId([
            'thread_id' => $threadId,
            'user_id' => $userId,
            'text' => $request->text,
            'attachments' => $request->has('attachments') ? json_encode($request->attachments) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('chat_messages')->where('id', $id)->first();
        return response()->json(['data' => $row], 201);
    }

    public function deleteMessage(Request $request, $threadId, $messageId): JsonResponse
    {
        $userId = (int) auth()->id();
        if (!$this->isMember($threadId, $userId)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $msg = DB::table('chat_messages')
            ->where('id', $messageId)
            ->where('thread_id', $threadId)
            ->first();
        if (!$msg) {
            return response()->json(['message' => 'Message not found'], 404);
        }
        if ((int) $msg->user_id !== $userId) {
            return response()->json(['message' => 'Can only delete own messages'], 403);
        }

        DB::table('chat_messages')->where('id', $messageId)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Deleted']);
    }

    public function reportMessage(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'reason' => 'required|string|max:65535',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $userId = (int) auth()->id();
        $msg = DB::table('chat_messages')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$msg) {
            return response()->json(['message' => 'Message not found'], 404);
        }
        if (!$this->isMember($msg->thread_id, $userId)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        DB::table('chat_reports')->insert([
            'thread_id' => $msg->thread_id,
            'message_id' => $msg->id,
            'reported_by' => $userId,
            'reason' => $request->reason,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Report submitted'], 201);
    }

    public function getSettings(Request $request, $id): JsonResponse
    {
        $userId = (int) auth()->id();
        if (!$this->isMember($id, $userId)) {
            return response()->json(['message' => 'Access denied'], 403);
        }
        $row = DB::table('chat_thread_settings')->where('thread_id', $id)->first();
        return response()->json(['data' => $row ? (array) $row : []]);
    }

    public function updateSettings(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'mode' => 'nullable|in:standard,announcements',
            'quiet_hours' => 'nullable|array',
            'quiet_hours.*.start' => 'required_with:quiet_hours|string|max:32',
            'quiet_hours.*.end' => 'required_with:quiet_hours|string|max:32',
            'attachments_enabled' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $thread = DB::table('chat_threads')->where('id', $id)->first();
        if (!$thread) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        $row = DB::table('chat_thread_settings')->where('thread_id', $id)->first();
        $payload = [
            'mode' => $request->input('mode', $row?->mode ?? 'standard'),
            'quiet_hours' => $request->has('quiet_hours') ? json_encode($request->quiet_hours) : ($row?->quiet_hours ?? null),
            'attachments_enabled' => $request->has('attachments_enabled') ? $request->boolean('attachments_enabled') : ($row?->attachments_enabled ?? true),
            'updated_at' => now(),
        ];
        if ($row) {
            DB::table('chat_thread_settings')->where('thread_id', $id)->update($payload);
        } else {
            $payload['thread_id'] = $id;
            $payload['created_at'] = now();
            $payload['attachments_enabled'] = $request->has('attachments_enabled') ? $request->boolean('attachments_enabled') : true;
            DB::table('chat_thread_settings')->insert($payload);
        }
        $out = DB::table('chat_thread_settings')->where('thread_id', $id)->first();
        return response()->json(['data' => (array) $out]);
    }

    public function complaints(Request $request): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('chat_reports as r')
            ->join('chat_threads as t', 't.id', '=', 'r.thread_id')
            ->when(Schema::hasColumn('chat_threads', 'tenant_id'), fn ($q) => $q->where('t.tenant_id', $tenantId))
            ->select('r.*')
            ->orderByDesc('r.created_at');
        if ($request->filled('status')) {
            $q->where('r.status', $request->status);
        }
        $items = $q->limit(200)->get();
        return response()->json(['data' => $items]);
    }

    public function reviewComplaint(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'status' => 'required|in:reviewed,closed',
            'notes' => 'nullable|string|max:65535',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $r = DB::table('chat_reports as r')
            ->join('chat_threads as t', 't.id', '=', 'r.thread_id')
            ->when(Schema::hasColumn('chat_threads', 'tenant_id'), fn ($q) => $q->where('t.tenant_id', $tenantId))
            ->where('r.id', $id)
            ->select('r.*')
            ->first();
        if (!$r) {
            return response()->json(['message' => 'Complaint not found'], 404);
        }

        DB::table('chat_reports')->where('id', $id)->update([
            'status' => $request->status,
            'updated_at' => now(),
        ]);
        $row = DB::table('chat_reports')->where('id', $id)->first();
        return response()->json(['data' => $row]);
    }

    private function isMember(int $threadId, int $userId): bool
    {
        return DB::table('chat_members')
            ->where('thread_id', $threadId)
            ->where('user_id', $userId)
            ->exists();
    }
}
