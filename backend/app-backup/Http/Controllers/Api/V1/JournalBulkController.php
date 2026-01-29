<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Grade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class JournalBulkController extends Controller
{
    /**
     * POST /api/journal/bulk/grades
     * Excel-like обновление оценок
     */
    public function bulkUpdateGrades(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => 'required|integer|exists:lessons,id',
            'grades' => 'required|array',
            'grades.*.student_user_id' => 'required|integer|exists:users,id',
            'grades.*.value' => 'nullable|numeric|min:1|max:5',
            'grades.*.grade_type' => 'nullable|string|in:control,homework,exam,other',
        ]);

        $lesson = Lesson::findOrFail($validated['lesson_id']);

        // TODO: Проверка прав доступа

        DB::beginTransaction();
        try {
            $updated = 0;
            $created = 0;

            foreach ($validated['grades'] as $gradeData) {
                if (!isset($gradeData['value']) || $gradeData['value'] === null) {
                    continue;
                }

                $grade = Grade::where('lesson_id', $lesson->id)
                    ->where('student_user_id', $gradeData['student_user_id'])
                    ->where('grade_type', $gradeData['grade_type'] ?? 'control')
                    ->first();

                if ($grade) {
                    $grade->update(['value' => $gradeData['value']]);
                    $updated++;
                } else {
                    Grade::create([
                        'lesson_id' => $lesson->id,
                        'student_user_id' => $gradeData['student_user_id'],
                        'value' => $gradeData['value'],
                        'grade_type' => $gradeData['grade_type'] ?? 'control',
                    ]);
                    $created++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Оценки обновлены',
                'created' => $created,
                'updated' => $updated,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Ошибка при обновлении оценок'], 500);
        }
    }

    /**
     * POST /api/journal/bulk/attendance
     * Excel-like обновление посещаемости
     */
    public function bulkUpdateAttendance(Request $request): JsonResponse
    {
        // TODO: Реализовать когда будет модель Attendance
        return response()->json(['message' => 'Not implemented'], 501);
    }
}

