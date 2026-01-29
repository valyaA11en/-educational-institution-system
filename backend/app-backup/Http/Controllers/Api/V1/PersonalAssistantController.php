<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PersonalAssistantController extends Controller
{
    public function __construct(
        private AssistantService $assistantService
    ) {}

    public function today(): JsonResponse
    {
        $user = Auth::user();

        // Определяем роль и вызываем соответствующий метод
        if ($user->hasRole('student') || $user->hasRole('студент')) {
            $data = $this->assistantService->getTodayForStudent($user->id);
        } elseif ($user->hasRole('преподаватель') || $user->hasRole('teacher')) {
            $data = $this->assistantService->getTodayForTeacher($user->id);
        } elseif ($user->hasRole('куратор') || $user->hasRole('curator')) {
            $data = $this->assistantService->getTodayForCurator($user->id);
        } elseif ($user->hasRole('admin')) {
            $data = $this->assistantService->getTodayForAdmin($user->id);
        } else {
            // Для других ролей возвращаем пустую структуру
            $data = [];
        }

        return response()->json([
            'data' => $data,
            'role' => $this->getUserRole($user),
        ]);
    }

    /**
     * Получить роль пользователя для ответа
     */
    private function getUserRole($user): string
    {
        if ($user->hasRole('student') || $user->hasRole('студент')) {
            return 'student';
        } elseif ($user->hasRole('преподаватель') || $user->hasRole('teacher')) {
            return 'teacher';
        } elseif ($user->hasRole('куратор') || $user->hasRole('curator')) {
            return 'curator';
        } elseif ($user->hasRole('admin')) {
            return 'admin';
        }
        return 'unknown';
    }
}


