<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class JournalGridController extends Controller
{
    /**
     * GET /api/journal/grid?lessonId=
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'lessonId' => 'required|integer|exists:lessons,id',
        ]);

        $lesson = Lesson::with(['scheduleItem.group', 'scheduleItem.subject'])
            ->findOrFail($request->input('lessonId'));

        // Проверка прав доступа
        if (!$this->canViewLesson(Auth::user(), $lesson)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $scheduleItem = $lesson->scheduleItem;
        $group = $scheduleItem->group ?? null;

        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        // Получаем студентов группы
        $studentIds = DB::table('group_members')
            ->where('group_id', $group->id)
            ->where('role_in_group', 'student')
            ->pluck('user_id');
        
        $students = User::whereIn('id', $studentIds)
            ->orderBy('fio')
            ->get();

        // Получаем оценки за урок
        $grades = Grade::where('lesson_id', $lesson->id)
            ->whereIn('student_user_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_user_id');

        // Получаем посещаемость
        $attendance = Attendance::where('lesson_id', $lesson->id)
            ->whereIn('student_user_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_user_id');

        $result = [
            'lesson' => [
                'id' => $lesson->id,
                'date' => $lesson->date->format('Y-m-d'),
                'subjectId' => $scheduleItem->subject_id,
                'groupId' => $group->id,
            ],
            'students' => $students->map(function ($student) {
                return [
                    'id' => $student->id,
                    'fio' => $student->fio,
                ];
            })->values(),
            'grades' => [],
            'attendance' => [],
        ];

        // Формируем grades по studentId
        foreach ($students as $student) {
            $studentGrades = $grades->get($student->id, collect());
            $result['grades'][$student->id] = $studentGrades->map(function ($grade) {
                return [
                    'id' => $grade->id,
                    'value' => $grade->value,
                    'weight' => $grade->weight,
                    'comment' => $grade->comment,
                    'createdAt' => $grade->created_at->format('Y-m-d H:i:s'),
                ];
            })->values()->toArray();
        }

        // Формируем attendance
        foreach ($students as $student) {
            $attendanceRecord = $attendance->get($student->id);
            $result['attendance'][$student->id] = [
                'status' => $attendanceRecord?->status ?? null,
                'reason' => $attendanceRecord?->reason ?? null,
            ];
        }

        return response()->json($result);
    }

    /**
     * POST /api/journal/grid/save
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lessonId' => 'required|integer|exists:lessons,id',
            'grades' => 'array',
            'grades.*.studentId' => 'required|integer|exists:users,id',
            'grades.*.value' => 'nullable|numeric|min:1|max:5',
            'grades.*.weight' => 'nullable|numeric|min:0|max:100',
            'grades.*.comment' => 'nullable|string|max:500',
            'grades.*.gradeId' => 'nullable|integer|exists:grades,id',
            'grades.*.reason' => 'nullable|string|max:500',
            'attendance' => 'array',
            'attendance.*.studentId' => 'required|integer|exists:users,id',
            'attendance.*.status' => 'nullable|string|in:present,absent,late',
            'attendance.*.reason' => 'nullable|string|max:500',
        ]);

        $lesson = Lesson::with('scheduleItem')->findOrFail($validated['lessonId']);
        $user = Auth::user();

        // Проверка прав доступа
        if (!$this->canEditLesson($user, $lesson)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        DB::beginTransaction();
        try {
            $updatedGrades = 0;
            $createdGrades = 0;
            $attendanceUpdated = 0;

            // Обработка оценок
            if (isset($validated['grades'])) {
                foreach ($validated['grades'] as $gradeData) {
                    if (!isset($gradeData['value']) || $gradeData['value'] === null) {
                        continue;
                    }

                    if (isset($gradeData['gradeId']) && $gradeData['gradeId']) {
                        // Update существующей оценки
                        $grade = Grade::findOrFail($gradeData['gradeId']);
                        
                        // Проверка ограничения на редактирование старых оценок
                        if (!$this->canEditGrade($user, $grade, $gradeData['reason'] ?? null)) {
                            DB::rollBack();
                            return response()->json([
                                'message' => 'Cannot edit old grade without reason or admin override',
                            ], 403);
                        }

                        $oldValue = $grade->value;
                        
                        $grade->update([
                            'value' => $gradeData['value'],
                            'weight' => $gradeData['weight'] ?? $grade->weight,
                            'comment' => $gradeData['comment'] ?? $grade->comment,
                        ]);

                        $updatedGrades++;

                        // Outbox событие
                        \App\Models\OutboxEvent::create([
                            'event_type' => 'grade.updated',
                            'entity_type' => Grade::class,
                            'entity_id' => $grade->id,
                            'payload_json' => [
                                'grade_id' => $grade->id,
                                'lesson_id' => $lesson->id,
                                'student_user_id' => $gradeData['studentId'],
                                'old_value' => $oldValue,
                                'new_value' => $gradeData['value'],
                            ],
                            'idempotency_key' => 'grade_updated_' . $grade->id . '_' . now()->timestamp,
                            'status' => 'new',
                        ]);
                    } else {
                        // Create новой оценки
                        $grade = Grade::create([
                            'lesson_id' => $lesson->id,
                            'student_user_id' => $gradeData['studentId'],
                            'value' => $gradeData['value'],
                            'weight' => $gradeData['weight'] ?? 1.0,
                            'comment' => $gradeData['comment'] ?? null,
                            'grade_type' => 'control',
                            'created_by' => $user->id,
                        ]);

                        $createdGrades++;

                        // Outbox событие
                        \App\Models\OutboxEvent::create([
                            'event_type' => 'grade.created',
                            'entity_type' => Grade::class,
                            'entity_id' => $grade->id,
                            'payload_json' => [
                                'grade_id' => $grade->id,
                                'lesson_id' => $lesson->id,
                                'student_user_id' => $gradeData['studentId'],
                                'value' => $gradeData['value'],
                            ],
                            'idempotency_key' => 'grade_created_' . $grade->id . '_' . now()->timestamp,
                            'status' => 'new',
                        ]);
                    }
                }
            }

            // Обработка посещаемости
            if (isset($validated['attendance'])) {
                foreach ($validated['attendance'] as $attendanceData) {
                    if (!isset($attendanceData['status']) || $attendanceData['status'] === null) {
                        continue;
                    }

                    $attendance = Attendance::updateOrCreate(
                        [
                            'lesson_id' => $lesson->id,
                            'student_user_id' => $attendanceData['studentId'],
                        ],
                        [
                            'status' => $attendanceData['status'],
                            'reason' => $attendanceData['reason'] ?? null,
                            'created_by' => $user->id,
                        ]
                    );

                    $attendanceUpdated++;

                    // Outbox событие
                    \App\Models\OutboxEvent::create([
                        'event_type' => 'attendance.updated',
                        'entity_type' => Attendance::class,
                        'entity_id' => $attendance->id,
                        'payload_json' => [
                            'attendance_id' => $attendance->id,
                            'lesson_id' => $lesson->id,
                            'student_user_id' => $attendanceData['studentId'],
                            'status' => $attendanceData['status'],
                        ],
                        'idempotency_key' => 'attendance_updated_' . $attendance->id . '_' . now()->timestamp,
                        'status' => 'new',
                    ]);
                }
            }

            // Audit логирование
            $this->logAudit($user, 'journal.grid.save', [
                'lesson_id' => $lesson->id,
                'grades_created' => $createdGrades,
                'grades_updated' => $updatedGrades,
                'attendance_updated' => $attendanceUpdated,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Saved successfully',
                'created' => $createdGrades,
                'updated' => $updatedGrades,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save journal grid', [
                'lesson_id' => $lesson->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Error saving data'], 500);
        }
    }

    /**
     * Проверка прав на просмотр урока
     */
    protected function canViewLesson($user, Lesson $lesson): bool
    {
        // TODO: Использовать Gate/Policy для проверки прав
        // Admin и методист могут видеть всё
        $roles = $user->roles()->pluck('name')->toArray();
        if (in_array('admin', $roles) || in_array('методист', $roles)) {
            return true;
        }

        // Преподаватель может видеть свои уроки
        if (in_array('преподаватель', $roles) || in_array('teacher', $roles)) {
            $scheduleItem = $lesson->scheduleItem;
            if ($scheduleItem && $scheduleItem->teacher_user_id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверка прав на редактирование урока
     */
    protected function canEditLesson($user, Lesson $lesson): bool
    {
        // TODO: Использовать Gate/Policy для проверки прав
        // Admin может редактировать всё
        $roles = $user->roles()->pluck('name')->toArray();
        if (in_array('admin', $roles)) {
            return true;
        }

        // Преподаватель может редактировать только свои уроки
        if (in_array('преподаватель', $roles) || in_array('teacher', $roles)) {
            $scheduleItem = $lesson->scheduleItem;
            if ($scheduleItem && $scheduleItem->teacher_user_id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверка возможности редактирования оценки
     */
    protected function canEditGrade($user, Grade $grade, ?string $reason = null): bool
    {
        // Admin всегда может редактировать
        $roles = $user->roles()->pluck('name')->toArray();
        if (in_array('admin', $roles)) {
            return true;
        }

        // Проверка ограничения по времени
        $maxEditDays = config('journal.max_edit_days', 7);
        $daysSinceCreation = $grade->created_at->diffInDays(now());

        if ($daysSinceCreation > $maxEditDays) {
            // Если превышен срок, требуется причина
            if (config('journal.require_reason_for_old_grade', true)) {
                if (!$reason || empty(trim($reason))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Логирование в audit
     */
    protected function logAudit($user, string $action, array $data): void
    {
        try {
            \App\Models\AuditLog::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'action' => $action,
                'entity' => Lesson::class,
                'entity_id' => $data['lesson_id'] ?? null,
                'before_json' => null,
                'after_json' => $data,
                'ip' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log audit', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
