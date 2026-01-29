<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\JournalExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class JournalController extends Controller
{
    public function __construct(
        private JournalExportService $exportService
    ) {}

    public function lessons(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'group_id' => 'nullable|exists:groups,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = $this->tenantId();
        $q = DB::table('lessons')
            ->join('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->where('lessons.tenant_id', $tenantId)
            ->select('lessons.id', 'lessons.schedule_item_id', 'lessons.date', 'lessons.topic', 'lessons.status', 'lessons.tenant_id',
                'schedule_items.group_id', 'schedule_items.subject_id', 'schedule_items.time_slot_id', 'schedule_items.subgroup_id');

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
        $q->orderBy('lessons.date')->orderBy('schedule_items.time_slot_id');
        $items = $q->get();

        return response()->json(['data' => $items]);
    }

    public function createLesson(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'schedule_item_id' => 'required|exists:schedule_items,id',
            'date' => 'required|date',
            'topic' => 'nullable|string|max:65535',
            'ktp_topic_id' => 'nullable|exists:curriculum_topics,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = $this->tenantId();
        $si = DB::table('schedule_items')->where('id', $request->schedule_item_id)->first();
        if (!$si || ($si->tenant_id && (int) $si->tenant_id !== (int) $tenantId)) {
            return response()->json(['message' => 'Schedule item not found or access denied'], 404);
        }

        $exists = DB::table('lessons')
            ->where('tenant_id', $tenantId)
            ->where('schedule_item_id', $request->schedule_item_id)
            ->where('date', $request->date)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'Lesson already exists for this schedule item and date', 'errors' => ['date' => ['Duplicate.']]], 422);
        }

        $id = DB::table('lessons')->insertGetId([
            'tenant_id' => $tenantId,
            'schedule_item_id' => $request->schedule_item_id,
            'date' => $request->date,
            'topic' => $request->topic,
            'ktp_topic_id' => $request->ktp_topic_id,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('lessons')->where('id', $id)->first();

        return response()->json(['data' => $row], 201);
    }

    public function updateLesson(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'topic' => 'nullable|string|max:65535',
            'ktp_topic_id' => 'nullable|exists:curriculum_topics,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = $this->tenantId();
        $lesson = DB::table('lessons')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        $upd = array_filter([
            'topic' => $request->topic,
            'ktp_topic_id' => $request->ktp_topic_id,
        ], fn ($v) => $v !== null);
        $upd['updated_at'] = now();

        DB::table('lessons')->where('id', $id)->update($upd);
        $row = DB::table('lessons')->where('id', $id)->first();

        return response()->json(['data' => $row]);
    }

    public function destroyLesson(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $lesson = DB::table('lessons')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        DB::table('lessons')->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function grades(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'lesson_id' => 'nullable|exists:lessons,id',
            'student_user_id' => 'nullable|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = $this->tenantId();
        $q = DB::table('grades')->where('grades.tenant_id', $tenantId);

        if ($request->filled('lesson_id')) {
            $q->where('grades.lesson_id', $request->lesson_id);
        }
        if ($request->filled('student_user_id')) {
            $q->where('grades.student_user_id', $request->student_user_id);
        }
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $q->join('lessons', 'grades.lesson_id', '=', 'lessons.id')
                ->where('lessons.tenant_id', $tenantId);
            if ($request->filled('date_from')) {
                $q->where('lessons.date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $q->where('lessons.date', '<=', $request->date_to);
            }
            $q->select('grades.*');
        }

        $items = $q->orderBy('grades.id')->get();

        return response()->json(['data' => $items]);
    }

    public function createGrade(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'lesson_id' => 'nullable|exists:lessons,id',
            'assignment_id' => 'nullable|exists:assignments,id',
            'student_user_id' => 'required|exists:users,id',
            'value' => 'required|integer|min:1|max:5',
            'weight' => 'nullable|integer|min:1',
            'grade_type' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:65535',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $lessonId = $request->lesson_id;
        $assignmentId = $request->assignment_id;
        if ((!$lessonId && !$assignmentId) || ($lessonId && $assignmentId)) {
            return response()->json(['message' => 'Exactly one of lesson_id or assignment_id is required', 'errors' => ['lesson_id' => ['Invalid.']]], 422);
        }

        $tenantId = $this->tenantId();
        if ($lessonId) {
            $lesson = DB::table('lessons')->where('id', $lessonId)->where('tenant_id', $tenantId)->first();
            if (!$lesson) {
                return response()->json(['message' => 'Lesson not found'], 404);
            }
        }
        // assignment tenant check if needed

        $id = DB::table('grades')->insertGetId([
            'tenant_id' => $tenantId,
            'lesson_id' => $lessonId,
            'assignment_id' => $assignmentId,
            'student_user_id' => $request->student_user_id,
            'value' => $request->value,
            'weight' => $request->weight ?? 1,
            'grade_type' => $request->grade_type,
            'comment' => $request->comment,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('grades')->where('id', $id)->first();

        return response()->json(['data' => $row], 201);
    }

    public function updateGrade(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'value' => 'sometimes|integer|min:1|max:5',
            'weight' => 'nullable|integer|min:1',
            'grade_type' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:65535',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = $this->tenantId();
        $grade = DB::table('grades')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$grade) {
            return response()->json(['message' => 'Grade not found'], 404);
        }

        $upd = array_filter([
            'value' => $request->value,
            'weight' => $request->weight,
            'grade_type' => $request->grade_type,
            'comment' => $request->comment,
        ], fn ($v) => $v !== null);
        $upd['updated_at'] = now();

        DB::table('grades')->where('id', $id)->update($upd);
        $row = DB::table('grades')->where('id', $id)->first();

        return response()->json(['data' => $row]);
    }

    public function destroyGrade(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $grade = DB::table('grades')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$grade) {
            return response()->json(['message' => 'Grade not found'], 404);
        }

        DB::table('grades')->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function attendance(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'lesson_id' => 'nullable|exists:lessons,id',
            'student_user_id' => 'nullable|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = $this->tenantId();
        $q = DB::table('attendance')
            ->join('lessons', 'attendance.lesson_id', '=', 'lessons.id')
            ->where('lessons.tenant_id', $tenantId)
            ->select('attendance.*');

        if ($request->filled('lesson_id')) {
            $q->where('attendance.lesson_id', $request->lesson_id);
        }
        if ($request->filled('student_user_id')) {
            $q->where('attendance.student_user_id', $request->student_user_id);
        }
        if ($request->filled('date_from')) {
            $q->where('lessons.date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->where('lessons.date', '<=', $request->date_to);
        }
        $items = $q->orderBy('attendance.id')->get();

        return response()->json(['data' => $items]);
    }

    public function createAttendance(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'lesson_id' => 'required|exists:lessons,id',
            'student_user_id' => 'required|exists:users,id',
            'status' => 'required|in:present,absent,late',
            'reason' => 'nullable|string|max:65535',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = $this->tenantId();
        $lesson = DB::table('lessons')->where('id', $request->lesson_id)->where('tenant_id', $tenantId)->first();
        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        $exists = DB::table('attendance')
            ->where('lesson_id', $request->lesson_id)
            ->where('student_user_id', $request->student_user_id)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'Attendance already exists for this lesson and student', 'errors' => ['student_user_id' => ['Duplicate.']]], 422);
        }

        $id = DB::table('attendance')->insertGetId([
            'tenant_id' => $tenantId,
            'lesson_id' => $request->lesson_id,
            'student_user_id' => $request->student_user_id,
            'status' => $request->status,
            'reason' => $request->reason,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('attendance')->where('id', $id)->first();

        return response()->json(['data' => $row], 201);
    }

    public function updateAttendance(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'status' => 'sometimes|in:present,absent,late',
            'reason' => 'nullable|string|max:65535',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = $this->tenantId();
        $att = DB::table('attendance')
            ->join('lessons', 'attendance.lesson_id', '=', 'lessons.id')
            ->where('attendance.id', $id)
            ->where('lessons.tenant_id', $tenantId)
            ->select('attendance.id')
            ->first();
        if (!$att) {
            return response()->json(['message' => 'Attendance not found'], 404);
        }

        $upd = array_filter([
            'status' => $request->status,
            'reason' => $request->reason,
        ], fn ($v) => $v !== null);
        $upd['updated_at'] = now();

        DB::table('attendance')->where('id', $id)->update($upd);
        $row = DB::table('attendance')->where('id', $id)->first();

        return response()->json(['data' => $row]);
    }

    public function destroyAttendance(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $att = DB::table('attendance')
            ->join('lessons', 'attendance.lesson_id', '=', 'lessons.id')
            ->where('attendance.id', $id)
            ->where('lessons.tenant_id', $tenantId)
            ->select('attendance.id')
            ->first();
        if (!$att) {
            return response()->json(['message' => 'Attendance not found'], 404);
        }

        DB::table('attendance')->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function reports(Request $request): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function exportGradeChanges(Request $request): StreamedResponse
    {
        $tenantId = $this->tenantId();
        $filters = [
            'student_id' => $request->query('student_id') ? (int) $request->query('student_id') : null,
            'subject_id' => $request->query('subject_id') ? (int) $request->query('subject_id') : null,
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        $filename = 'grade_changes_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(
            function () use ($tenantId, $filters): void {
                $bom = "\xEF\xBB\xBF";
                echo $bom;
                foreach ($this->exportService->gradeChangesCsv($tenantId, $filters) as $line) {
                    echo $line;
                }
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ],
            'attachment'
        );
    }

    private function tenantId(): int
    {
        return (int) auth()->user()->tenant_id;
    }
}
