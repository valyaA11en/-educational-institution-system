<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentPortfolioItem;
use App\Services\StudentPortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentPortfolioController extends Controller
{
    public function __construct(
        private StudentPortfolioService $portfolioService
    ) {}

    /**
     * GET /api/students/{id}/portfolio
     */
    public function index(int $studentId, Request $request): JsonResponse
    {
        $user = Auth::user();

        // Проверка прав доступа
        if (!$this->canViewPortfolio($user, $studentId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $filters = [];
        if ($request->has('type')) {
            $filters['type'] = $request->input('type');
        }
        if ($request->has('is_featured')) {
            $filters['is_featured'] = $request->boolean('is_featured');
        }

        $portfolio = $this->portfolioService->getPortfolio($studentId, $filters);

        return response()->json(['data' => $portfolio]);
    }

    /**
     * PATCH /api/students/{id}/portfolio/{itemId}
     */
    public function update(int $studentId, int $itemId, Request $request): JsonResponse
    {
        $user = Auth::user();
        $item = StudentPortfolioItem::findOrFail($itemId);

        // Проверка, что элемент принадлежит указанному студенту
        if ($item->student_user_id !== $studentId) {
            return response()->json(['message' => 'Item does not belong to this student'], 404);
        }

        // Проверка прав: студент может изменять только is_featured
        if ($item->student_user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'is_featured' => 'sometimes|boolean',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $item->update($validated);

        return response()->json(['data' => $item]);
    }

    /**
     * POST /api/students/{id}/portfolio/manual
     */
    public function storeManual(int $studentId, Request $request): JsonResponse
    {
        $user = Auth::user();

        // Проверка прав: только teacher/admin могут добавлять вручную
        if (!$user->hasRole('преподаватель') && !$user->hasRole('teacher') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Проверка, что студент существует
        $student = \App\Models\User::findOrFail($studentId);

        $validated = $request->validate([
            'type' => 'required|string|in:assignment,contest,certificate,achievement',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'related_entity_type' => 'nullable|string',
            'related_entity_id' => 'nullable|integer',
            'file_id' => 'nullable|integer|exists:files,id',
            'is_featured' => 'boolean',
        ]);

        $item = StudentPortfolioItem::create([
            'tenant_id' => app('tenant_id'),
            'student_user_id' => $studentId,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'related_entity_type' => $validated['related_entity_type'] ?? null,
            'related_entity_id' => $validated['related_entity_id'] ?? null,
            'file_id' => $validated['file_id'] ?? null,
            'is_featured' => $validated['is_featured'] ?? false,
        ]);

        return response()->json(['data' => $item], 201);
    }

    /**
     * Проверка прав на просмотр портфолио
     */
    protected function canViewPortfolio($user, int $studentId): bool
    {
        // Админ и методист могут видеть всё
        if ($user->hasRole('admin') || $user->hasRole('методист')) {
            return true;
        }

        // Студент может видеть только своё портфолио
        if ($user->id === $studentId) {
            $isStudent = $user->hasRole('student') || $user->hasRole('студент');
            if ($isStudent) {
                return true;
            }
        }

        // Родитель может видеть портфолио своих детей
        // TODO: Реализовать связь родитель-ребёнок в модели User
        // if ($user->hasRole('родитель') || $user->hasRole('parent')) {
        //     $children = $user->children()->pluck('id')->toArray();
        //     if (in_array($studentId, $children)) {
        //         return true;
        //     }
        // }

        // Преподаватель и куратор могут видеть портфолио студентов своих групп
        if ($user->hasRole('преподаватель') || $user->hasRole('teacher') || $user->hasRole('куратор') || $user->hasRole('curator')) {
            $student = \App\Models\User::find($studentId);
            if ($student) {
                $studentGroups = $student->groups()->pluck('groups.id')->toArray();
                $userGroups = $user->groups()->pluck('groups.id')->toArray();
                
                if (count(array_intersect($studentGroups, $userGroups)) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * GET /api/students/{id}/portfolio/export
     */
    public function export(int $studentId, Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = Auth::user();

        // Проверка прав доступа
        if (!$this->canViewPortfolio($user, $studentId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $filters = [];
        if ($request->has('type')) {
            $filters['type'] = $request->input('type');
        }
        if ($request->has('is_featured')) {
            $filters['is_featured'] = $request->boolean('is_featured');
        }

        $portfolio = $this->portfolioService->getPortfolio($studentId, $filters);
        $student = \App\Models\User::findOrFail($studentId);
        $tenant = app('tenant') ?? \App\Models\Tenant::first();

        // Группировка по типам
        $groupedByType = [];
        foreach ($portfolio as $item) {
            $type = $item['type'];
            if (!isset($groupedByType[$type])) {
                $groupedByType[$type] = [];
            }
            $groupedByType[$type][] = $item;
        }

        $data = [
            'tenant' => $tenant,
            'student' => $student,
            'portfolio' => $groupedByType,
            'filters' => $filters,
            'export_date' => now(),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('print.student_portfolio', $data);
        $filename = sprintf('portfolio_%s_%s.pdf', $studentId, now()->format('Y-m-d'));

        return $pdf->download($filename);
    }
}

