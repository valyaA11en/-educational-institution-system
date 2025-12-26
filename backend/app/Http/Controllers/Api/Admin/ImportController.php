<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Role;
use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use App\Models\Subject;
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
                        'errors' => $validator->errors()->toArray(),
                    ];
                    continue;
                }

                // Find time slot
                $timeSlot = TimeSlot::where('name', $rowData['time_slot'])->first();
                if (!$timeSlot) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ['time_slot' => ['Временной слот не найден: ' . $rowData['time_slot']]],
                    ];
                    continue;
                }

                // Find group
                $group = Group::where('name', $rowData['group'])->first();
                if (!$group) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ['group' => ['Группа не найдена: ' . $rowData['group']]],
                    ];
                    continue;
                }

                // Find subject
                $subject = Subject::where('name', $rowData['subject'])->first();
                if (!$subject) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ['subject' => ['Предмет не найден: ' . $rowData['subject']]],
                    ];
                    continue;
                }

                // Find teacher
                $teacher = User::where('email', $rowData['teacher_email'])->first();
                if (!$teacher) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ['teacher_email' => ['Преподаватель не найден: ' . $rowData['teacher_email']]],
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
                            'errors' => ['room' => ['Аудитория не найдена: ' . $rowData['room']]],
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
}

