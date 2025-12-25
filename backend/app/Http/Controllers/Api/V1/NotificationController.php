<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // TODO: получить список уведомлений пользователя
        return response()->json(['message' => 'Not implemented']);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        // TODO: отметить уведомление как прочитанное
        return response()->json(['message' => 'Not implemented']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        // TODO: отметить все уведомления как прочитанные
        return response()->json(['message' => 'Not implemented']);
    }

    public function settings(Request $request): JsonResponse
    {
        // TODO: получить настройки уведомлений
        return response()->json(['message' => 'Not implemented']);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        // TODO: обновить настройки уведомлений
        return response()->json(['message' => 'Not implemented']);
    }
}

