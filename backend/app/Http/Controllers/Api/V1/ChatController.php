<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function threads(Request $request): JsonResponse
    {
        // TODO: получить список тредов чата
        return response()->json(['message' => 'Not implemented']);
    }

    public function createThread(Request $request): JsonResponse
    {
        // TODO: создать тред чата
        return response()->json(['message' => 'Not implemented']);
    }

    public function messages(Request $request, int $threadId): JsonResponse
    {
        // TODO: получить сообщения треда
        return response()->json(['message' => 'Not implemented']);
    }

    public function sendMessage(Request $request, int $threadId): JsonResponse
    {
        // TODO: отправить сообщение в тред
        return response()->json(['message' => 'Not implemented']);
    }

    public function deleteMessage(Request $request, int $threadId, int $messageId): JsonResponse
    {
        // TODO: удалить сообщение
        return response()->json(['message' => 'Not implemented']);
    }
}

