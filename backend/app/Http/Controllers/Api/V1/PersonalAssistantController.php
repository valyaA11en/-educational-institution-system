<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PersonalAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PersonalAssistantController extends Controller
{
    public function __construct(
        private PersonalAssistantService $assistantService
    ) {}

    public function today(): JsonResponse
    {
        $user = Auth::user();
        $tasks = $this->assistantService->getTodayTasks($user);

        return response()->json([
            'data' => $tasks,
            'count' => count($tasks),
            'urgent_count' => collect($tasks)->where('priority', 'urgent')->count(),
        ]);
    }
}

