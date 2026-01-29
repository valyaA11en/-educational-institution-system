<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function journal(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $v = Validator::make($request->all(), [
            'group_id' => 'nullable|exists:groups,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) $user->tenant_id;
        $q = DB::table('lessons')
            ->join('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->where('lessons.tenant_id', $tenantId);
        if ($request->filled('group_id')) {
            $q->where('schedule_items.group_id', $request->group_id);
        }
        if ($request->filled('subject_id')) {
            $q->where('schedule_items.subject_id', $request->subject_id);
        }
        if ($request->filled('date_from')) {
            $q->where('lessons.date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->where('lessons.date', '<=', $request->date_to);
        }

        $lessonsCount = (clone $q)->count();
        $gradesCount = DB::table('grades')
            ->join('lessons', 'grades.lesson_id', '=', 'lessons.id')
            ->join('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->where('grades.tenant_id', $tenantId)
            ->whereNotNull('grades.lesson_id')
            ->when($request->filled('group_id'), fn ($q) => $q->where('schedule_items.group_id', $request->group_id))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('schedule_items.subject_id', $request->subject_id))
            ->when($request->filled('date_from'), fn ($q) => $q->where('lessons.date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->where('lessons.date', '<=', $request->date_to))
            ->count();
        $attendanceCount = DB::table('attendance')
            ->join('lessons', 'attendance.lesson_id', '=', 'lessons.id')
            ->join('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->where('lessons.tenant_id', $tenantId)
            ->when($request->filled('group_id'), fn ($q) => $q->where('schedule_items.group_id', $request->group_id))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('schedule_items.subject_id', $request->subject_id))
            ->when($request->filled('date_from'), fn ($q) => $q->where('lessons.date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->where('lessons.date', '<=', $request->date_to))
            ->count();

        return response()->json([
            'data' => [
                'summary' => [
                    'lessons_count' => $lessonsCount,
                    'grades_count' => $gradesCount,
                    'attendance_count' => $attendanceCount,
                ],
                'filters' => $request->only(['group_id', 'subject_id', 'date_from', 'date_to']),
            ],
        ]);
    }

    public function schedule(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $v = Validator::make($request->all(), [
            'version_id' => 'nullable|exists:schedule_versions,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'group_id' => 'nullable|exists:groups,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) $user->tenant_id;
        $q = DB::table('schedule_items')
            ->join('schedule_versions', 'schedule_items.version_id', '=', 'schedule_versions.id')
            ->where('schedule_versions.tenant_id', $tenantId);
        if ($request->filled('version_id')) {
            $q->where('schedule_items.version_id', $request->version_id);
        }
        if ($request->filled('date_from')) {
            $q->where('schedule_items.date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->where('schedule_items.date', '<=', $request->date_to);
        }
        if ($request->filled('group_id')) {
            $q->where('schedule_items.group_id', $request->group_id);
        }

        $total = (clone $q)->count();
        $items = $q->orderBy('schedule_items.date')
            ->orderBy('schedule_items.time_slot_id')
            ->select('schedule_items.*')
            ->limit(500)
            ->get();

        return response()->json([
            'data' => [
                'summary' => ['total' => $total, 'returned' => $items->count()],
                'items' => $items,
                'filters' => $request->only(['version_id', 'date_from', 'date_to', 'group_id']),
            ],
        ]);
    }

    public function gradeSheet(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $v = Validator::make($request->all(), [
            'group_id' => 'nullable|exists:groups,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'student_user_id' => 'nullable|exists:users,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) $user->tenant_id;
        $q = DB::table('grades')
            ->join('users', 'grades.student_user_id', '=', 'users.id')
            ->leftJoin('lessons', 'grades.lesson_id', '=', 'lessons.id')
            ->leftJoin('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->leftJoin('assignments', 'grades.assignment_id', '=', 'assignments.id')
            ->leftJoin('subjects as s1', 'schedule_items.subject_id', '=', 's1.id')
            ->leftJoin('subjects as s2', 'assignments.subject_id', '=', 's2.id')
            ->where('grades.tenant_id', $tenantId)
            ->select(
                'grades.id',
                'grades.student_user_id',
                'users.fio as student_fio',
                'grades.value',
                'grades.weight',
                'grades.lesson_id',
                'grades.assignment_id',
                DB::raw('COALESCE(s1.name, s2.name) as subject_name')
            );
        if ($request->filled('group_id')) {
            $q->whereExists(function ($ex) use ($request): void {
                $ex->select(DB::raw(1))
                    ->from('group_members')
                    ->whereColumn('group_members.user_id', 'grades.student_user_id')
                    ->where('group_members.group_id', $request->group_id);
            });
        }
        if ($request->filled('subject_id')) {
            $q->where(function ($q) use ($request): void {
                $q->where('schedule_items.subject_id', $request->subject_id)
                    ->orWhere('assignments.subject_id', $request->subject_id);
            });
        }
        if ($request->filled('student_user_id')) {
            $q->where('grades.student_user_id', $request->student_user_id);
        }

        $items = $q->orderBy('users.fio')->orderBy('grades.id')->limit(1000)->get();

        return response()->json([
            'data' => [
                'grades' => $items,
                'filters' => $request->only(['group_id', 'subject_id', 'student_user_id']),
            ],
        ]);
    }

    public function order(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $v = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'status' => 'nullable|in:draft,on_review,approved,signed,archived',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) $user->tenant_id;
        $q = DB::table('documents')
            ->where('type', 'order')
            ->when(Schema::hasColumn('documents', 'tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId));
        if ($request->filled('date_from')) {
            $q->where('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->where('date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        $total = (clone $q)->count();
        $items = $q->orderByDesc('date')->orderBy('id')->limit(500)->get();

        return response()->json([
            'data' => [
                'summary' => ['total' => $total, 'returned' => $items->count()],
                'orders' => $items,
                'filters' => $request->only(['date_from', 'date_to', 'status']),
            ],
        ]);
    }
}
