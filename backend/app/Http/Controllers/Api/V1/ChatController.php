<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function threads(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ChatThread::class);
        // TODO: получить список тредов чата
        return response()->json(['message' => 'Not implemented']);
    }

    public function createThread(Request $request): JsonResponse
    {
        $this->authorize('create', ChatThread::class);
        // TODO: создать тред чата
        return response()->json(['message' => 'Not implemented']);
    }

    public function messages(Request $request, int $threadId): JsonResponse
    {
        $thread = ChatThread::findOrFail($threadId);
        $this->authorize('view', $thread);
        // TODO: получить сообщения треда
        return response()->json(['message' => 'Not implemented']);
    }

    public function sendMessage(Request $request, int $threadId): JsonResponse
    {
        $thread = ChatThread::findOrFail($threadId);
        $this->authorize('write', $thread);
        // TODO: отправить сообщение в тред
        return response()->json(['message' => 'Not implemented']);
    }

    public function deleteMessage(Request $request, int $threadId, int $messageId): JsonResponse
    {
        $thread = ChatThread::findOrFail($threadId);
        $this->authorize('update', $thread);
        // TODO: удалить сообщение
        return response()->json(['message' => 'Not implemented']);
    }
}

