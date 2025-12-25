<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // TODO: получить список тикетов
        return response()->json(['message' => 'Not implemented']);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: создать тикет
        return response()->json(['message' => 'Not implemented']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // TODO: получить тикет
        return response()->json(['message' => 'Not implemented']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: обновить тикет
        return response()->json(['message' => 'Not implemented']);
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        // TODO: получить сообщения тикета
        return response()->json(['message' => 'Not implemented']);
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        // TODO: отправить сообщение в тикет
        return response()->json(['message' => 'Not implemented']);
    }
}

