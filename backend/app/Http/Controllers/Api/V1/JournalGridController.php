<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JournalGridController extends Controller
{
    /**
     * Get journal grid data (students x lessons with grades and attendance)
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
            'subject_id' => 'required|exists:subjects,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'subgroup_id' => 'nullable|exists:subgroups,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tenantId = auth()->user()->tenant_id;
            $groupId = $request->group_id;
            $subjectId = $request->subject_id;
            $subgroupId = $request->subgroup_id;
            $dateFrom = Carbon::parse($request->date_from);
            $dateTo = Carbon::parse($request->date_to);

            // Get students in the group
            $students = DB::table('group_members')
                ->join('users', 'group_members.user_id', '=', 'users.id')
                ->where('group_members.group_id', $groupId)
                ->where('group_members.role_in_group', 'student')
                ->where('users.tenant_id', $tenantId)
                ->select('users.id', 'users.fio')
                ->orderBy('users.fio')
                ->get();

            // Get lessons for the period
            $lessons = DB::table('lessons')
                ->join('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
                ->where('schedule_items.group_id', $groupId)
                ->where('schedule_items.subject_id', $subjectId)
                ->whereBetween('lessons.date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')])
                ->select(
                    'lessons.id',
                    'lessons.date',
                    'lessons.topic',
                    'schedule_items.time_slot_id',
                    'schedule_items.subgroup_id'
                )
                ->orderBy('lessons.date')
                ->orderBy('schedule_items.time_slot_id')
                ->get();

            // Filter lessons by subgroup if specified
            if ($subgroupId) {
                $lessons = $lessons->filter(function ($lesson) use ($subgroupId) {
                    return $lesson->subgroup_id == $subgroupId || $lesson->subgroup_id === null;
                })->values();
            }

            // Get all grades for these lessons
            $gradeIds = $lessons->pluck('id')->toArray();
            $grades = [];
            if (!empty($gradeIds)) {
                $gradesData = DB::table('grades')
                    ->whereIn('lesson_id', $gradeIds)
                    ->select('id', 'lesson_id', 'student_user_id', 'value', 'weight', 'grade_type', 'comment')
                    ->get();

                foreach ($gradesData as $grade) {
                    $key = $grade->lesson_id . '_' . $grade->student_user_id;
                    if (!isset($grades[$key])) {
                        $grades[$key] = [];
                    }
                    $grades[$key][] = [
                        'id' => $grade->id,
                        'value' => $grade->value,
                        'weight' => $grade->weight,
                        'grade_type' => $grade->grade_type,
                        'comment' => $grade->comment,
                    ];
                }
            }

            // Get all attendance for these lessons
            $attendance = [];
            if (!empty($gradeIds)) {
                $attendanceData = DB::table('attendance')
                    ->whereIn('lesson_id', $gradeIds)
                    ->select('lesson_id', 'student_user_id', 'status', 'reason')
                    ->get();

                foreach ($attendanceData as $att) {
                    $key = $att->lesson_id . '_' . $att->student_user_id;
                    $attendance[$key] = [
                        'status' => $att->status,
                        'reason' => $att->reason,
                    ];
                }
            }

            // Build grid structure
            $grid = [
                'students' => $students->map(function ($student) use ($lessons, $grades, $attendance) {
                    $studentLessons = [];
                    foreach ($lessons as $lesson) {
                        $key = $lesson->id . '_' . $student->id;
                        $studentLessons[] = [
                            'lesson_id' => $lesson->id,
                            'date' => $lesson->date,
                            'topic' => $lesson->topic,
                            'grades' => $grades[$key] ?? [],
                            'attendance' => $attendance[$key] ?? null,
                        ];
                    }
                    return [
                        'id' => $student->id,
                        'fio' => $student->fio,
                        'lessons' => $studentLessons,
                    ];
                }),
                'lessons' => $lessons->map(function ($lesson) {
                    return [
                        'id' => $lesson->id,
                        'date' => $lesson->date,
                        'topic' => $lesson->topic,
                        'time_slot_id' => $lesson->time_slot_id,
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => $grid
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load journal grid: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save journal grid data (grades and attendance for a lesson)
     */
    public function save(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required|exists:lessons,id',
            'grades' => 'nullable|array',
            'grades.*.student_user_id' => 'required|exists:users,id',
            'grades.*.value' => 'required|integer|min:1|max:5',
            'grades.*.weight' => 'nullable|integer|min:1',
            'grades.*.grade_type' => 'nullable|string',
            'grades.*.comment' => 'nullable|string',
            'grades.*.id' => 'nullable|exists:grades,id', // For updates
            'attendance' => 'nullable|array',
            'attendance.*.student_user_id' => 'required|exists:users,id',
            'attendance.*.status' => 'required|in:present,absent,late',
            'attendance.*.reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $lessonId = $request->lesson_id;
            $userId = auth()->id();
            $saved = 0;
            $errors = [];

            // Save/update grades
            if ($request->has('grades') && is_array($request->grades)) {
                foreach ($request->grades as $gradeData) {
                    try {
                        if (isset($gradeData['id'])) {
                            // Update existing grade
                            DB::table('grades')
                                ->where('id', $gradeData['id'])
                                ->where('lesson_id', $lessonId)
                                ->update([
                                    'value' => $gradeData['value'],
                                    'weight' => $gradeData['weight'] ?? 1,
                                    'grade_type' => $gradeData['grade_type'] ?? null,
                                    'comment' => $gradeData['comment'] ?? null,
                                    'updated_at' => now(),
                                ]);
                        } else {
                            // Create new grade
                            DB::table('grades')->insert([
                                'lesson_id' => $lessonId,
                                'student_user_id' => $gradeData['student_user_id'],
                                'value' => $gradeData['value'],
                                'weight' => $gradeData['weight'] ?? 1,
                                'grade_type' => $gradeData['grade_type'] ?? null,
                                'comment' => $gradeData['comment'] ?? null,
                                'created_by' => $userId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        $saved++;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'student_id' => $gradeData['student_user_id'],
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }

            // Save/update attendance
            if ($request->has('attendance') && is_array($request->attendance)) {
                foreach ($request->attendance as $attData) {
                    try {
                        $exists = DB::table('attendance')
                            ->where('lesson_id', $lessonId)
                            ->where('student_user_id', $attData['student_user_id'])
                            ->exists();

                        if ($exists) {
                            // Update existing attendance
                            DB::table('attendance')
                                ->where('lesson_id', $lessonId)
                                ->where('student_user_id', $attData['student_user_id'])
                                ->update([
                                    'status' => $attData['status'],
                                    'reason' => $attData['reason'] ?? null,
                                    'updated_at' => now(),
                                ]);
                        } else {
                            // Create new attendance
                            DB::table('attendance')->insert([
                                'lesson_id' => $lessonId,
                                'student_user_id' => $attData['student_user_id'],
                                'status' => $attData['status'],
                                'reason' => $attData['reason'] ?? null,
                                'created_by' => $userId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        $saved++;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'student_id' => $attData['student_user_id'],
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Saved {$saved} records",
                'saved' => $saved,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save journal data: ' . $e->getMessage()
            ], 500);
        }
    }
}
