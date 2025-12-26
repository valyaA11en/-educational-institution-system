<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Models\ChatMessage;
use App\Models\ChatComplaint;
use App\Models\ChatReport;
use App\Models\ChatThreadSettings;
use App\Services\Outbox\OutboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function __construct(
        private OutboxService $outboxService
    ) {
    }
    public function threads(Request $request): JsonResponse
    {
        $user = auth()->user();

        $threads = ChatThread::whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with(['members'])->get();

        return response()->json($threads);
    }

    public function createThread(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:group,private'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'is_announcement' => ['sometimes', 'boolean'],
            'quiet_hours_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'max_attachment_size' => ['sometimes', 'nullable', 'integer'],
            'allowed_attachment_types' => ['sometimes', 'nullable', 'array'],
        ]);

        DB::beginTransaction();
        try {
            $thread = ChatThread::create([
                'type' => $validated['type'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_announcement' => $validated['is_announcement'] ?? false,
                'quiet_hours_start' => $validated['quiet_hours_start'] ?? null,
                'quiet_hours_end' => $validated['quiet_hours_end'] ?? null,
                'max_attachment_size' => $validated['max_attachment_size'] ?? null,
                'allowed_attachment_types' => $validated['allowed_attachment_types'] ?? null,
            ]);

            $memberIds = array_unique(array_merge($validated['member_ids'], [auth()->id()]));
            $thread->members()->attach($memberIds);

            DB::commit();
            return response()->json($thread->load('members'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function messages(Request $request, int $threadId): JsonResponse
    {
        $thread = ChatThread::findOrFail($threadId);
        $this->authorize('view', $thread);

        $messages = ChatMessage::where('thread_id', $threadId)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->paginate($request->integer('per_page', 50));

        return response()->json($messages);
    }

    public function sendMessage(Request $request, int $threadId): JsonResponse
    {
        $thread = ChatThread::with('settings')->findOrFail($threadId);
        $this->authorize('write', $thread);

        $user = auth()->user();
        $settings = $thread->settings;

        // Check if user is moderator
        $isModerator = $thread->members()
            ->where('user_id', $user->id)
            ->wherePivot('role_in_chat', 'moderator')
            ->exists()
            || $user->permissions()->where('code', 'chat.moderate')->exists();

        // Check announcements mode
        if ($settings && $settings->mode === 'announcements' && !$isModerator) {
            return response()->json([
                'message' => 'В режиме объявлений писать могут только модераторы.',
            ], 403);
        }

        // Check quiet hours from settings
        if ($settings && $settings->quiet_hours) {
            $now = now();
            $currentTime = $now->format('H:i');
            
            foreach ($settings->quiet_hours as $quietPeriod) {
                $start = $quietPeriod['start'] ?? null;
                $end = $quietPeriod['end'] ?? null;
                
                if ($start && $end && !$isModerator) {
                    if ($start <= $end) {
                        if ($currentTime >= $start && $currentTime <= $end) {
                            return response()->json([
                                'message' => 'Тихие часы. Сообщения запрещены.',
                            ], 403);
                        }
                    } else {
                        // Crosses midnight
                        if ($currentTime >= $start || $currentTime <= $end) {
                            return response()->json([
                                'message' => 'Тихие часы. Сообщения запрещены.',
                            ], 403);
                        }
                    }
                }
            }
        }

        // Legacy quiet hours check (from thread itself)
        if ($thread->quiet_hours_start && $thread->quiet_hours_end && !$isModerator) {
            $now = now()->format('H:i');
            $start = $thread->quiet_hours_start->format('H:i');
            $end = $thread->quiet_hours_end->format('H:i');
            
            if ($start <= $end) {
                if ($now >= $start && $now <= $end) {
                    return response()->json([
                        'message' => 'Тихие часы. Сообщения запрещены.',
                    ], 403);
                }
            } else {
                if ($now >= $start || $now <= $end) {
                    return response()->json([
                        'message' => 'Тихие часы. Сообщения запрещены.',
                    ], 403);
                }
            }
        }

        $validated = $request->validate([
            'text' => ['required', 'string'],
            'attachment_ids' => ['sometimes', 'array'],
            'attachment_ids.*' => ['integer', 'exists:files,id'],
        ]);

        // Check attachments_enabled
        if (!empty($validated['attachment_ids'])) {
            if ($settings && !$settings->attachments_enabled) {
                return response()->json([
                    'message' => 'Вложения запрещены в этом чате.',
                ], 403);
            }

            $files = \App\Models\File::whereIn('id', $validated['attachment_ids'])->get();
            
            foreach ($files as $file) {
                if ($thread->max_attachment_size && $file->size > $thread->max_attachment_size) {
                    return response()->json([
                        'message' => "Файл {$file->original_name} превышает максимальный размер",
                    ], 422);
                }

                if ($thread->allowed_attachment_types) {
                    $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
                    $mimeType = $file->mime;
                    
                    $allowed = false;
                    foreach ($thread->allowed_attachment_types as $allowedType) {
                        if ($extension === strtolower($allowedType) || $mimeType === $allowedType) {
                            $allowed = true;
                            break;
                        }
                    }

                    if (!$allowed) {
                        return response()->json([
                            'message' => "Тип файла {$file->original_name} не разрешен",
                        ], 422);
                    }
                }
            }
        }

        $message = ChatMessage::create([
            'thread_id' => $threadId,
            'user_id' => auth()->id(),
            'text' => $validated['text'],
        ]);

        if (!empty($validated['attachment_ids'])) {
            $message->files()->attach($validated['attachment_ids']);
        }

        return response()->json($message->load('user'), 201);
    }

    public function deleteMessage(Request $request, int $threadId, int $messageId): JsonResponse
    {
        $message = ChatMessage::where('thread_id', $threadId)->findOrFail($messageId);
        $this->authorize('moderate', ChatThread::findOrFail($threadId));

        $message->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function reportMessage(Request $request, int $threadId): JsonResponse
    {
        $thread = ChatThread::findOrFail($threadId);
        $this->authorize('view', $thread);

        $validated = $request->validate([
            'messageId' => ['required', 'integer', 'exists:chat_messages,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $message = ChatMessage::where('thread_id', $threadId)
            ->findOrFail($validated['messageId']);

        $report = ChatReport::create([
            'thread_id' => $threadId,
            'message_id' => $validated['messageId'],
            'reported_by' => auth()->id(),
            'reason' => $validated['reason'],
            'status' => 'open',
        ]);

        // Outbox event
        $this->outboxService->record(
            \App\Support\Events\EventTypes::CHAT_REPORT_CREATED,
            auth()->id(),
            'chat_report',
            $report->id,
            [
                'thread_id' => $threadId,
                'message_id' => $validated['messageId'],
                'reason' => $validated['reason'],
            ]
        );

        return response()->json($report->load(['thread', 'message', 'reporter']), 201);
    }

    public function getSettings(int $threadId): JsonResponse
    {
        $thread = ChatThread::findOrFail($threadId);
        $this->authorize('view', $thread);

        $settings = ChatThreadSettings::where('thread_id', $threadId)->first();

        if (!$settings) {
            return response()->json(null, 404);
        }

        return response()->json($settings->load('thread'));
    }

    public function updateSettings(Request $request, int $threadId): JsonResponse
    {
        $thread = ChatThread::findOrFail($threadId);
        $this->authorize('update', $thread);

        $validated = $request->validate([
            'mode' => ['sometimes', 'in:standard,announcements'],
            'quiet_hours' => ['sometimes', 'nullable', 'array'],
            'quiet_hours.*.start' => ['required_with:quiet_hours', 'string', 'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'],
            'quiet_hours.*.end' => ['required_with:quiet_hours', 'string', 'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'],
            'attachments_enabled' => ['sometimes', 'boolean'],
        ]);

        $settings = ChatThreadSettings::firstOrCreate(
            ['thread_id' => $threadId],
            [
                'mode' => 'standard',
                'attachments_enabled' => true,
            ]
        );

        $oldSettings = $settings->toArray();

        if (isset($validated['mode'])) {
            $settings->mode = $validated['mode'];
        }
        if (isset($validated['quiet_hours'])) {
            $settings->quiet_hours = $validated['quiet_hours'];
        }
        if (isset($validated['attachments_enabled'])) {
            $settings->attachments_enabled = $validated['attachments_enabled'];
        }

        $settings->save();

        // Outbox event
        $this->outboxService->record(
            \App\Support\Events\EventTypes::CHAT_THREAD_SETTINGS_CHANGED,
            auth()->id(),
            'chat_thread',
            $threadId,
            [
                'thread_id' => $threadId,
                'before' => $oldSettings,
                'after' => $settings->toArray(),
            ]
        );

        return response()->json($settings->load('thread'));
    }

    public function complaints(Request $request): JsonResponse
    {
        $this->authorize('moderate', ChatThread::class);

        $query = ChatComplaint::with(['thread', 'message', 'reporter']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $complaints = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($complaints);
    }

    public function reviewComplaint(Request $request, int $id): JsonResponse
    {
        $this->authorize('moderate', ChatThread::class);

        $complaint = ChatComplaint::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:resolved,dismissed'],
            'review_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $complaint->update([
            'status' => $validated['status'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        return response()->json($complaint->load(['thread', 'message', 'reporter', 'reviewer']));
    }
}
