<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Group;
use App\Models\Subject;
use App\Models\Room;
use App\Models\TimeSlot;
use App\Models\Subgroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ImportController extends Controller
{
    /**
     * Import users from CSV/JSON
     */
    public function importUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.fio' => 'required|string',
            'data.*.email' => 'nullable|email',
            'data.*.phone' => 'nullable|string',
            'data.*.password' => 'nullable|string|min:8',
            'data.*.status' => 'nullable|in:active,blocked',
            'data.*.role_ids' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $imported = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($request->data as $index => $userData) {
                try {
                    // Generate password if not provided
                    $password = $userData['password'] ?? Str::random(12);

                    $user = User::create([
                        'fio' => $userData['fio'],
                        'email' => $userData['email'] ?? null,
                        'phone' => $userData['phone'] ?? null,
                        'password_hash' => Hash::make($password),
                        'status' => $userData['status'] ?? 'active',
                        'tenant_id' => auth()->user()->tenant_id,
                    ]);

                    // Assign roles if provided
                    if (isset($userData['role_ids']) && is_array($userData['role_ids'])) {
                        $user->roles()->sync($userData['role_ids']);
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Imported {$imported} users",
                'imported' => $imported,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import schedule items
     */
    public function importSchedule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'version_id' => 'required|exists:schedule_versions,id',
            'data' => 'required|array',
            'data.*.date' => 'required|date',
            'data.*.time_slot_id' => 'required|exists:time_slots,id',
            'data.*.group_id' => 'required|exists:groups,id',
            'data.*.subgroup_id' => 'nullable|exists:subgroups,id',
            'data.*.subject_id' => 'required|exists:subjects,id',
            'data.*.teacher_user_id' => 'required|exists:users,id',
            'data.*.room_id' => 'required|exists:rooms,id',
            'data.*.override_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $imported = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($request->data as $index => $itemData) {
                try {
                    DB::table('schedule_items')->insert([
                        'version_id' => $request->version_id,
                        'date' => $itemData['date'],
                        'time_slot_id' => $itemData['time_slot_id'],
                        'group_id' => $itemData['group_id'],
                        'subgroup_id' => $itemData['subgroup_id'] ?? null,
                        'subject_id' => $itemData['subject_id'],
                        'teacher_user_id' => $itemData['teacher_user_id'],
                        'room_id' => $itemData['room_id'],
                        'override_reason' => $itemData['override_reason'] ?? null,
                        'created_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Imported {$imported} schedule items",
                'imported' => $imported,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import grades
     */
    public function importGrades(Request $request): JsonResponse
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $imported = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($request->data as $index => $gradeData) {
                try {
                    // Validate that either lesson_id or assignment_id is provided
                    if (empty($gradeData['lesson_id']) && empty($gradeData['assignment_id'])) {
                        throw new \Exception('Either lesson_id or assignment_id must be provided');
                    }

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

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Imported {$imported} grades",
                'imported' => $imported,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import attendance records
     */
    public function importAttendance(Request $request): JsonResponse
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

        $imported = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($request->data as $index => $attendanceData) {
                try {
                    // Check if attendance already exists
                    $exists = DB::table('attendance')
                        ->where('lesson_id', $attendanceData['lesson_id'])
                        ->where('student_user_id', $attendanceData['student_user_id'])
                        ->exists();

                    if ($exists) {
                        // Update existing record
                        DB::table('attendance')
                            ->where('lesson_id', $attendanceData['lesson_id'])
                            ->where('student_user_id', $attendanceData['student_user_id'])
                            ->update([
                                'status' => $attendanceData['status'],
                                'reason' => $attendanceData['reason'] ?? null,
                                'updated_at' => now(),
                            ]);
                    } else {
                        // Create new record
                        DB::table('attendance')->insert([
                            'lesson_id' => $attendanceData['lesson_id'],
                            'student_user_id' => $attendanceData['student_user_id'],
                            'status' => $attendanceData['status'],
                            'reason' => $attendanceData['reason'] ?? null,
                            'created_by' => auth()->id(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Imported {$imported} attendance records",
                'imported' => $imported,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import KTP (curriculum plans)
     */
    public function importKtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.subject_id' => 'required|exists:subjects,id',
            'data.*.group_id' => 'required|exists:groups,id',
            'data.*.term_id' => 'required|exists:terms,id',
            'data.*.teacher_user_id' => 'nullable|exists:users,id',
            'data.*.name' => 'required|string',
            'data.*.description' => 'nullable|string',
            'data.*.is_template' => 'nullable|boolean',
            'data.*.template_id' => 'nullable|exists:curriculum_plans,id',
            'data.*.topics' => 'nullable|array',
            'data.*.topics.*.order' => 'required|integer',
            'data.*.topics.*.title' => 'required|string',
            'data.*.topics.*.description' => 'nullable|string',
            'data.*.topics.*.hours_total' => 'required|integer',
            'data.*.topics.*.hours_lecture' => 'nullable|integer',
            'data.*.topics.*.hours_practice' => 'nullable|integer',
            'data.*.topics.*.hours_lab' => 'nullable|integer',
            'data.*.topics.*.control_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $imported = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($request->data as $index => $ktpData) {
                try {
                    // Create curriculum plan
                    $curriculumPlanId = DB::table('curriculum_plans')->insertGetId([
                        'subject_id' => $ktpData['subject_id'],
                        'group_id' => $ktpData['group_id'],
                        'term_id' => $ktpData['term_id'],
                        'teacher_user_id' => $ktpData['teacher_user_id'] ?? null,
                        'name' => $ktpData['name'],
                        'description' => $ktpData['description'] ?? null,
                        'is_template' => $ktpData['is_template'] ?? false,
                        'template_id' => $ktpData['template_id'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Import topics if provided
                    if (isset($ktpData['topics']) && is_array($ktpData['topics'])) {
                        foreach ($ktpData['topics'] as $topicData) {
                            DB::table('curriculum_topics')->insert([
                                'curriculum_plan_id' => $curriculumPlanId,
                                'order' => $topicData['order'],
                                'title' => $topicData['title'],
                                'description' => $topicData['description'] ?? null,
                                'hours_total' => $topicData['hours_total'],
                                'hours_lecture' => $topicData['hours_lecture'] ?? 0,
                                'hours_practice' => $topicData['hours_practice'] ?? 0,
                                'hours_lab' => $topicData['hours_lab'] ?? 0,
                                'control_type' => $topicData['control_type'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Imported {$imported} curriculum plans",
                'imported' => $imported,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
