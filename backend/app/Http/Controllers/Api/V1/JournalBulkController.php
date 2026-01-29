<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class JournalBulkController extends Controller
{
    /**
     * Bulk update grades for multiple lessons/students
     */
    public function bulkUpdateGrades(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.lesson_id' => 'nullable|exists:lessons,id',
            'data.*.assignment_id' => 'nullable|exists:assignments,id',
            'data.*.student_user_id' => 'required|exists:users,id',
            'data.*.value' => 'required|integer|min:1|max:5',
            'data.*.weight' => 'nullable|integer|min:1',
            'data.*.grade_type' => 'nullable|string',
            'data.*.comment' => 'nullable|string',
            'data.*.id' => 'nullable|exists:grades,id', // For updates
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $updated = 0;
        $created = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($request->data as $index => $gradeData) {
                try {
                    // Validate that either lesson_id or assignment_id is provided
                    if (empty($gradeData['lesson_id']) && empty($gradeData['assignment_id'])) {
                        throw new \Exception('Either lesson_id or assignment_id must be provided');
                    }

                    if (isset($gradeData['id'])) {
                        // Update existing grade
                        $updateData = [
                            'value' => $gradeData['value'],
                            'updated_at' => now(),
                        ];

                        if (isset($gradeData['weight'])) {
                            $updateData['weight'] = $gradeData['weight'];
                        }
                        if (isset($gradeData['grade_type'])) {
                            $updateData['grade_type'] = $gradeData['grade_type'];
                        }
                        if (isset($gradeData['comment'])) {
                            $updateData['comment'] = $gradeData['comment'];
                        }

                        DB::table('grades')
                            ->where('id', $gradeData['id'])
                            ->update($updateData);

                        $updated++;
                    } else {
                        // Create new grade
                        DB::table('grades')->insert([
                            'lesson_id' => $gradeData['lesson_id'] ?? null,
                            'assignment_id' => $gradeData['assignment_id'] ?? null,
                            'student_user_id' => $gradeData['student_user_id'],
                            'value' => $gradeData['value'],
                            'weight' => $gradeData['weight'] ?? 1,
                            'grade_type' => $gradeData['grade_type'] ?? null,
                            'comment' => $gradeData['comment'] ?? null,
                            'created_by' => auth()->id(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $created++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'student_id' => $gradeData['student_user_id'] ?? null,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Processed grades: {$created} created, {$updated} updated",
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Bulk update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update attendance for multiple lessons/students
     */
    public function bulkUpdateAttendance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.lesson_id' => 'required|exists:lessons,id',
            'data.*.student_user_id' => 'required|exists:users,id',
            'data.*.status' => 'required|in:present,absent,late',
            'data.*.reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $updated = 0;
        $created = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($request->data as $index => $attData) {
                try {
                    $exists = DB::table('attendance')
                        ->where('lesson_id', $attData['lesson_id'])
                        ->where('student_user_id', $attData['student_user_id'])
                        ->exists();

                    if ($exists) {
                        // Update existing attendance
                        DB::table('attendance')
                            ->where('lesson_id', $attData['lesson_id'])
                            ->where('student_user_id', $attData['student_user_id'])
                            ->update([
                                'status' => $attData['status'],
                                'reason' => $attData['reason'] ?? null,
                                'updated_at' => now(),
                            ]);

                        $updated++;
                    } else {
                        // Create new attendance
                        DB::table('attendance')->insert([
                            'lesson_id' => $attData['lesson_id'],
                            'student_user_id' => $attData['student_user_id'],
                            'status' => $attData['status'],
                            'reason' => $attData['reason'] ?? null,
                            'created_by' => auth()->id(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $created++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'lesson_id' => $attData['lesson_id'] ?? null,
                        'student_id' => $attData['student_user_id'] ?? null,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Processed attendance: {$created} created, {$updated} updated",
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Bulk update failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
