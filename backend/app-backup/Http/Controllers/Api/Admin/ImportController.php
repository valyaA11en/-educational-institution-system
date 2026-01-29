<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CurriculumPlan;
use App\Models\CurriculumTopic;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function importUsers(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');
        $data = Excel::toArray([], $file);

        if (empty($data) || empty($data[0])) {
            return response()->json(['message' => 'Файл пуст'], 400);
        }

        $rows = $data[0];
        // Skip header row
        $headerRow = array_shift($rows);
        
        $errors = [];
        $successCount = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because of header row and 0-based index

                // Convert numeric array to associative array
                $rowData = [
                    'fio' => $row[0] ?? '',
                    'email' => $row[1] ?? '',
                    'phone' => $row[2] ?? '',
                    'role' => $row[3] ?? '',
                    'group' => $row[4] ?? '',
                ];

                $validator = Validator::make($rowData, [
                    'fio' => ['required', 'string', 'max:255'],
                    'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
                    'phone' => ['nullable', 'string', 'max:20'],
                    'role' => ['required', 'string'],
                    'group' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $validator->errors()->toArray(),
                    ];
                    continue;
                }

                // Find or create role
                $role = Role::where('name', $rowData['role'])->first();
                if (!$role) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ['role' => ['Роль не найдена: ' . $rowData['role']]],
                    ];
                    continue;
                }

                // Create user
                $user = User::create([
                    'fio' => $rowData['fio'],
                    'email' => $rowData['email'] ?: null,
                    'phone' => $rowData['phone'] ?: null,
                    'password_hash' => Hash::make('password'), // Default password
                    'status' => 'active',
                ]);

                // Assign role
                $user->roles()->attach($role->id);

                // Add to group if specified
                if (!empty($rowData['group'])) {
                    $group = Group::where('name', $rowData['group'])->first();
                    if ($group) {
                        $user->groups()->attach($group->id, ['role_in_group' => 'student']);
                    } else {
                        $errors[] = [
                            'row' => $rowNumber,
                            'errors' => ['group' => ['Группа не найдена: ' . $rowData['group']]],
                        ];
                    }
                }

                $successCount++;
            }

            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Импорт завершен с ошибками',
                    'success_count' => $successCount,
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();
            return response()->json([
                'message' => 'Импорт успешно завершен',
                'success_count' => $successCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка импорта: ' . $e->getMessage(),
                'errors' => $errors,
            ], 500);
        }
    }

    public function importSchedule(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'versionId' => ['required', 'integer', 'exists:schedule_versions,id'],
        ]);

        $version = ScheduleVersion::findOrFail($request->input('versionId'));
        $file = $request->file('file');
        $data = Excel::toArray([], $file);

        if (empty($data) || empty($data[0])) {
            return response()->json(['message' => 'Файл пуст'], 400);
        }

        $rows = $data[0];
        // Skip header row
        $headerRow = array_shift($rows);
        
        $errors = [];
        $successCount = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because of header row and 0-based index

                // Convert numeric array to associative array
                $rowData = [
                    'date' => $row[0] ?? '',
                    'time_slot' => $row[1] ?? '',
                    'group' => $row[2] ?? '',
                    'subject' => $row[3] ?? '',
                    'teacher_email' => $row[4] ?? '',
                    'room' => $row[5] ?? '',
                ];

                $validator = Validator::make($rowData, [
                    'date' => ['required', 'date'],
                    'time_slot' => ['required', 'string'],
                    'group' => ['required', 'string'],
                    'subject' => ['required', 'string'],
                    'teacher_email' => ['required', 'email'],
                    'room' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => implode(', ', $validator->errors()->all()),
                    ];
                    continue;
                }

                // Find time slot
                $timeSlot = TimeSlot::where('name', $rowData['time_slot'])->first();
                if (!$timeSlot) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Временной слот не найден: ' . $rowData['time_slot'],
                    ];
                    continue;
                }

                // Find group
                $group = Group::where('name', $rowData['group'])->first();
                if (!$group) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Группа не найдена: ' . $rowData['group'],
                    ];
                    continue;
                }

                // Find subject
                $subject = Subject::where('name', $rowData['subject'])->first();
                if (!$subject) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Предмет не найден: ' . $rowData['subject'],
                    ];
                    continue;
                }

                // Find teacher
                $teacher = User::where('email', $rowData['teacher_email'])->first();
                if (!$teacher) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Преподаватель не найден: ' . $rowData['teacher_email'],
                    ];
                    continue;
                }

                // Find room (optional)
                $room = null;
                if (!empty($rowData['room'])) {
                    $room = \App\Models\Room::where('name', $rowData['room'])->first();
                    if (!$room) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'message' => 'Аудитория не найдена: ' . $rowData['room'],
                        ];
                        continue;
                    }
                }

                // Create schedule item
                ScheduleItem::create([
                    'version_id' => $version->id,
                    'date' => $rowData['date'],
                    'time_slot_id' => $timeSlot->id,
                    'group_id' => $group->id,
                    'subject_id' => $subject->id,
                    'teacher_user_id' => $teacher->id,
                    'room_id' => $room?->id,
                    'created_by' => auth()->id(),
                ]);

                $successCount++;
            }

            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Импорт завершен с ошибками',
                    'success_count' => $successCount,
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();
            return response()->json([
                'message' => 'Импорт успешно завершен',
                'success_count' => $successCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка импорта: ' . $e->getMessage(),
                'errors' => $errors,
            ], 500);
        }
    }

    /**
     * Import grades from XLSX
     * POST /api/admin/import/grades-xlsx
     * Columns: date, group, subject, student_email, grade_value, weight, comment, teacher_email
     */
    public function importGrades(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'dryRun' => ['sometimes', 'boolean'],
        ]);

        $dryRun = $request->boolean('dryRun', false);
        $tenantId = app('tenant_id');
        $file = $request->file('file');
        $data = Excel::toArray([], $file);

        if (empty($data) || empty($data[0])) {
            return response()->json(['message' => 'Файл пуст'], 400);
        }

        $rows = $data[0];
        // Skip header row
        array_shift($rows);
        
        $errors = [];
        $successCount = 0;

        if (!$dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because of header row and 0-based index

                // Convert numeric array to associative array
                $rowData = [
                    'date' => $row[0] ?? '',
                    'group' => $row[1] ?? '',
                    'subject' => $row[2] ?? '',
                    'student_email' => $row[3] ?? '',
                    'grade_value' => $row[4] ?? '',
                    'weight' => $row[5] ?? '',
                    'comment' => $row[6] ?? '',
                    'teacher_email' => $row[7] ?? '',
                ];

                $validator = Validator::make($rowData, [
                    'date' => ['required', 'date'],
                    'group' => ['required', 'string'],
                    'subject' => ['required', 'string'],
                    'student_email' => ['required', 'email'],
                    'grade_value' => ['required', 'numeric', 'min:1', 'max:5'],
                    'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
                    'comment' => ['nullable', 'string', 'max:1000'],
                    'teacher_email' => ['nullable', 'email'],
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => implode(', ', $validator->errors()->all()),
                    ];
                    continue;
                }

                // Find group
                $group = Group::where('tenant_id', $tenantId)
                    ->where('name', $rowData['group'])
                    ->first();
                if (!$group) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Группа не найдена: ' . $rowData['group'],
                    ];
                    continue;
                }

                // Find subject
                $subject = Subject::where('tenant_id', $tenantId)
                    ->where('name', $rowData['subject'])
                    ->first();
                if (!$subject) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Предмет не найден: ' . $rowData['subject'],
                    ];
                    continue;
                }

                // Find student
                $student = User::where('tenant_id', $tenantId)
                    ->where('email', $rowData['student_email'])
                    ->first();
                if (!$student) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Студент не найден: ' . $rowData['student_email'],
                    ];
                    continue;
                }

                // Find lesson by date, group, and subject
                $lesson = Lesson::where('tenant_id', $tenantId)
                    ->whereDate('date', $rowData['date'])
                    ->whereHas('scheduleItem', function ($query) use ($group, $subject) {
                        $query->where('group_id', $group->id)
                            ->where('subject_id', $subject->id);
                    })
                    ->first();

                if (!$lesson) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Урок не найден для даты: ' . $rowData['date'] . ', группа: ' . $rowData['group'] . ', предмет: ' . $rowData['subject'],
                    ];
                    continue;
                }

                // Find teacher if specified
                $teacherId = null;
                if (!empty($rowData['teacher_email'])) {
                    $teacher = User::where('tenant_id', $tenantId)
                        ->where('email', $rowData['teacher_email'])
                        ->first();
                    if (!$teacher) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'message' => 'Преподаватель не найден: ' . $rowData['teacher_email'],
                        ];
                        continue;
                    }
                    $teacherId = $teacher->id;
                } else {
                    $teacherId = auth()->id();
                }

                if (!$dryRun) {
                    Grade::create([
                        'lesson_id' => $lesson->id,
                        'student_user_id' => $student->id,
                        'value' => $rowData['grade_value'],
                        'weight' => $rowData['weight'] ?? 1.0,
                        'comment' => $rowData['comment'] ?? null,
                        'created_by' => $teacherId,
                    ]);
                }

                $successCount++;
            }

            if (!empty($errors)) {
                if (!$dryRun) {
                    DB::rollBack();
                }
                return response()->json([
                    'message' => $dryRun ? 'Проверка завершена с ошибками' : 'Импорт завершен с ошибками',
                    'dry_run' => $dryRun,
                    'success_count' => $successCount,
                    'errors' => $errors,
                ], 422);
            }

            if (!$dryRun) {
                DB::commit();
            }
            return response()->json([
                'message' => $dryRun ? 'Проверка успешно завершена' : 'Импорт успешно завершен',
                'dry_run' => $dryRun,
                'success_count' => $successCount,
            ]);
        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            return response()->json([
                'message' => 'Ошибка импорта: ' . $e->getMessage(),
                'errors' => $errors,
            ], 500);
        }
    }

    /**
     * Import attendance from XLSX
     * POST /api/admin/import/attendance-xlsx
     * Columns: date, group, student_email, status, reason
     */
    public function importAttendance(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'dryRun' => ['sometimes', 'boolean'],
        ]);

        $dryRun = $request->boolean('dryRun', false);
        $tenantId = app('tenant_id');
        $file = $request->file('file');
        $data = Excel::toArray([], $file);

        if (empty($data) || empty($data[0])) {
            return response()->json(['message' => 'Файл пуст'], 400);
        }

        $rows = $data[0];
        // Skip header row
        array_shift($rows);
        
        $errors = [];
        $successCount = 0;

        if (!$dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because of header row and 0-based index

                // Convert numeric array to associative array
                $rowData = [
                    'date' => $row[0] ?? '',
                    'group' => $row[1] ?? '',
                    'student_email' => $row[2] ?? '',
                    'status' => $row[3] ?? '',
                    'reason' => $row[4] ?? '',
                ];

                $validator = Validator::make($rowData, [
                    'date' => ['required', 'date'],
                    'group' => ['required', 'string'],
                    'student_email' => ['required', 'email'],
                    'status' => ['required', 'string', 'in:present,absent,late,excused'],
                    'reason' => ['nullable', 'string', 'max:500'],
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => implode(', ', $validator->errors()->all()),
                    ];
                    continue;
                }

                // Find group
                $group = Group::where('tenant_id', $tenantId)
                    ->where('name', $rowData['group'])
                    ->first();
                if (!$group) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Группа не найдена: ' . $rowData['group'],
                    ];
                    continue;
                }

                // Find student
                $student = User::where('tenant_id', $tenantId)
                    ->where('email', $rowData['student_email'])
                    ->first();
                if (!$student) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Студент не найден: ' . $rowData['student_email'],
                    ];
                    continue;
                }

                // Find lesson by date and group (first lesson of the day for this group)
                $scheduleItem = \App\Models\ScheduleItem::where('tenant_id', $tenantId)
                    ->whereDate('date', $rowData['date'])
                    ->where('group_id', $group->id)
                    ->orderBy('date')
                    ->first();

                if (!$scheduleItem) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Позиция расписания не найдена для даты: ' . $rowData['date'] . ', группа: ' . $rowData['group'],
                    ];
                    continue;
                }

                // Find or create lesson
                $lesson = Lesson::firstOrCreate(
                    [
                        'schedule_item_id' => $scheduleItem->id,
                        'date' => $rowData['date'],
                    ],
                    [
                        'created_by' => auth()->id(),
                    ]
                );

                if (!$lesson) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Урок не найден для даты: ' . $rowData['date'] . ', группа: ' . $rowData['group'],
                    ];
                    continue;
                }

                if (!$dryRun) {
                    Attendance::updateOrCreate(
                        [
                            'lesson_id' => $lesson->id,
                            'student_user_id' => $student->id,
                        ],
                        [
                            'status' => $rowData['status'],
                            'reason' => $rowData['reason'] ?? null,
                            'created_by' => auth()->id(),
                        ]
                    );
                }

                $successCount++;
            }

            if (!empty($errors)) {
                if (!$dryRun) {
                    DB::rollBack();
                }
                return response()->json([
                    'message' => $dryRun ? 'Проверка завершена с ошибками' : 'Импорт завершен с ошибками',
                    'dry_run' => $dryRun,
                    'success_count' => $successCount,
                    'errors' => $errors,
                ], 422);
            }

            if (!$dryRun) {
                DB::commit();
            }
            return response()->json([
                'message' => $dryRun ? 'Проверка успешно завершена' : 'Импорт успешно завершен',
                'dry_run' => $dryRun,
                'success_count' => $successCount,
            ]);
        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            return response()->json([
                'message' => 'Ошибка импорта: ' . $e->getMessage(),
                'errors' => $errors,
            ], 500);
        }
    }

    /**
     * Import KTP topics from XLSX
     * POST /api/admin/import/ktp-xlsx
     * Columns: group, subject, term, order_no, title, hours, control_type, planned_date_from, planned_date_to
     */
    public function importKtp(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'dryRun' => ['sometimes', 'boolean'],
        ]);

        $dryRun = $request->boolean('dryRun', false);
        $tenantId = app('tenant_id');
        $file = $request->file('file');
        $data = Excel::toArray([], $file);

        if (empty($data) || empty($data[0])) {
            return response()->json(['message' => 'Файл пуст'], 400);
        }

        $rows = $data[0];
        // Skip header row
        array_shift($rows);
        
        $errors = [];
        $successCount = 0;

        if (!$dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because of header row and 0-based index

                // Convert numeric array to associative array
                $rowData = [
                    'group' => $row[0] ?? '',
                    'subject' => $row[1] ?? '',
                    'term' => $row[2] ?? '',
                    'order_no' => $row[3] ?? '',
                    'title' => $row[4] ?? '',
                    'hours' => $row[5] ?? '',
                    'control_type' => $row[6] ?? '',
                    'planned_date_from' => $row[7] ?? '',
                    'planned_date_to' => $row[8] ?? '',
                ];

                $validator = Validator::make($rowData, [
                    'group' => ['required', 'string'],
                    'subject' => ['required', 'string'],
                    'term' => ['required', 'string'],
                    'order_no' => ['required', 'integer', 'min:1'],
                    'title' => ['required', 'string', 'max:500'],
                    'hours' => ['required', 'numeric', 'min:0'],
                    'control_type' => ['nullable', 'string', 'max:100'],
                    'planned_date_from' => ['nullable', 'date'],
                    'planned_date_to' => ['nullable', 'date', 'after_or_equal:planned_date_from'],
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => implode(', ', $validator->errors()->all()),
                    ];
                    continue;
                }

                // Find group
                $group = Group::where('tenant_id', $tenantId)
                    ->where('name', $rowData['group'])
                    ->first();
                if (!$group) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Группа не найдена: ' . $rowData['group'],
                    ];
                    continue;
                }

                // Find subject
                $subject = Subject::where('tenant_id', $tenantId)
                    ->where('name', $rowData['subject'])
                    ->first();
                if (!$subject) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Предмет не найден: ' . $rowData['subject'],
                    ];
                    continue;
                }

                // Find term
                $term = Term::where('tenant_id', $tenantId)
                    ->where('name', $rowData['term'])
                    ->first();
                if (!$term) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Семестр не найден: ' . $rowData['term'],
                    ];
                    continue;
                }

                // Find or create CurriculumPlan
                $plan = CurriculumPlan::where('group_id', $group->id)
                    ->where('subject_id', $subject->id)
                    ->where('term_id', $term->id)
                    ->first();

                if (!$plan) {
                    if (!$dryRun) {
                        $plan = CurriculumPlan::create([
                            'group_id' => $group->id,
                            'subject_id' => $subject->id,
                            'term_id' => $term->id,
                            'name' => 'КТП',
                            'status' => 'draft',
                            'created_by' => auth()->id(),
                        ]);
                    } else {
                        // In dry run, we skip creating the plan but continue validation
                        $successCount++;
                        continue;
                    }
                }

                if (!$dryRun) {
                    // Find or create CurriculumTopic
                    $topic = CurriculumTopic::where('curriculum_plan_id', $plan->id)
                        ->where('order_no', $rowData['order_no'])
                        ->first();

                    $topicData = [
                        'title' => $rowData['title'],
                        'hours' => $rowData['hours'],
                        'hours_total' => $rowData['hours'],
                        'order' => $rowData['order_no'], // For backward compatibility
                        'control_type' => $rowData['control_type'] ?? null,
                        'planned_date_from' => $rowData['planned_date_from'] ? date('Y-m-d', strtotime($rowData['planned_date_from'])) : null,
                        'planned_date_to' => $rowData['planned_date_to'] ? date('Y-m-d', strtotime($rowData['planned_date_to'])) : null,
                    ];

                    if ($topic) {
                        $topic->update($topicData);
                    } else {
                        $topicData['curriculum_plan_id'] = $plan->id;
                        $topicData['order_no'] = $rowData['order_no'];
                        CurriculumTopic::create($topicData);
                    }
                }

                $successCount++;
            }

            if (!empty($errors)) {
                if (!$dryRun) {
                    DB::rollBack();
                }
                return response()->json([
                    'message' => $dryRun ? 'Проверка завершена с ошибками' : 'Импорт завершен с ошибками',
                    'dry_run' => $dryRun,
                    'success_count' => $successCount,
                    'errors' => $errors,
                ], 422);
            }

            if (!$dryRun) {
                DB::commit();
            }
            return response()->json([
                'message' => $dryRun ? 'Проверка успешно завершена' : 'Импорт успешно завершен',
                'dry_run' => $dryRun,
                'success_count' => $successCount,
            ]);
        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            return response()->json([
                'message' => 'Ошибка импорта: ' . $e->getMessage(),
                'errors' => $errors,
            ], 500);
        }
    }
}
