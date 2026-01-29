<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    /**
     * Export users to JSON/CSV format
     */
    public function exportUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'nullable|in:json,csv',
            'tenant_id' => 'nullable|exists:tenants,id',
            'status' => 'nullable|in:active,blocked',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $format = $request->get('format', 'json');
        $query = User::with(['roles', 'tenants']);

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        } else {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'fio' => $user->fio,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'tenant_id' => $user->tenant_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'created_at' => $user->created_at->toIso8601String(),
            ];
        });

        if ($format === 'csv') {
            // Generate CSV content
            $csv = "ID,FIO,Email,Phone,Status,Tenant ID,Roles,Created At\n";
            foreach ($users as $user) {
                $csv .= sprintf(
                    "%s,%s,%s,%s,%s,%s,%s,%s\n",
                    $user['id'],
                    '"' . str_replace('"', '""', $user['fio']) . '"',
                    $user['email'] ?? '',
                    $user['phone'] ?? '',
                    $user['status'],
                    $user['tenant_id'] ?? '',
                    '"' . implode(';', $user['roles']) . '"',
                    $user['created_at']
                );
            }

            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
        }

        return response()->json([
            'success' => true,
            'data' => $users,
            'count' => $users->count()
        ]);
    }

    /**
     * Export schedule items
     */
    public function exportSchedule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'nullable|in:json,csv',
            'version_id' => 'nullable|exists:schedule_versions,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'group_id' => 'nullable|exists:groups,id',
            'teacher_user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $format = $request->get('format', 'json');
        $query = DB::table('schedule_items')
            ->join('schedule_versions', 'schedule_items.version_id', '=', 'schedule_versions.id')
            ->join('groups', 'schedule_items.group_id', '=', 'groups.id')
            ->join('subjects', 'schedule_items.subject_id', '=', 'subjects.id')
            ->join('users', 'schedule_items.teacher_user_id', '=', 'users.id')
            ->join('rooms', 'schedule_items.room_id', '=', 'rooms.id')
            ->join('time_slots', 'schedule_items.time_slot_id', '=', 'time_slots.id')
            ->leftJoin('subgroups', 'schedule_items.subgroup_id', '=', 'subgroups.id')
            ->select(
                'schedule_items.id',
                'schedule_items.version_id',
                'schedule_items.date',
                'schedule_items.time_slot_id',
                'time_slots.start_time',
                'time_slots.end_time',
                'schedule_items.group_id',
                'groups.name as group_name',
                'schedule_items.subgroup_id',
                'subgroups.name as subgroup_name',
                'schedule_items.subject_id',
                'subjects.name as subject_name',
                'schedule_items.teacher_user_id',
                'users.fio as teacher_name',
                'schedule_items.room_id',
                'rooms.name as room_name',
                'schedule_items.override_reason',
                'schedule_items.created_at'
            );

        if ($request->has('version_id')) {
            $query->where('schedule_items.version_id', $request->version_id);
        }

        if ($request->has('date_from')) {
            $query->where('schedule_items.date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('schedule_items.date', '<=', $request->date_to);
        }

        if ($request->has('group_id')) {
            $query->where('schedule_items.group_id', $request->group_id);
        }

        if ($request->has('teacher_user_id')) {
            $query->where('schedule_items.teacher_user_id', $request->teacher_user_id);
        }

        // Filter by tenant
        $tenantId = auth()->user()->tenant_id;
        $query->where('groups.tenant_id', $tenantId);

        $scheduleItems = $query->orderBy('schedule_items.date')
            ->orderBy('time_slots.start_time')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'version_id' => $item->version_id,
                    'date' => $item->date,
                    'time_slot' => [
                        'id' => $item->time_slot_id,
                        'start_time' => $item->start_time,
                        'end_time' => $item->end_time,
                    ],
                    'group' => [
                        'id' => $item->group_id,
                        'name' => $item->group_name,
                    ],
                    'subgroup' => $item->subgroup_id ? [
                        'id' => $item->subgroup_id,
                        'name' => $item->subgroup_name,
                    ] : null,
                    'subject' => [
                        'id' => $item->subject_id,
                        'name' => $item->subject_name,
                    ],
                    'teacher' => [
                        'id' => $item->teacher_user_id,
                        'name' => $item->teacher_name,
                    ],
                    'room' => [
                        'id' => $item->room_id,
                        'name' => $item->room_name,
                    ],
                    'override_reason' => $item->override_reason,
                    'created_at' => $item->created_at,
                ];
            });

        if ($format === 'csv') {
            // Generate CSV content
            $csv = "ID,Date,Time,Group,Subgroup,Subject,Teacher,Room,Override Reason\n";
            foreach ($scheduleItems as $item) {
                $csv .= sprintf(
                    "%s,%s,%s-%s,%s,%s,%s,%s,%s,%s\n",
                    $item['id'],
                    $item['date'],
                    $item['time_slot']['start_time'],
                    $item['time_slot']['end_time'],
                    '"' . str_replace('"', '""', $item['group']['name']) . '"',
                    $item['subgroup'] ? '"' . str_replace('"', '""', $item['subgroup']['name']) . '"' : '',
                    '"' . str_replace('"', '""', $item['subject']['name']) . '"',
                    '"' . str_replace('"', '""', $item['teacher']['name']) . '"',
                    '"' . str_replace('"', '""', $item['room']['name']) . '"',
                    $item['override_reason'] ? '"' . str_replace('"', '""', $item['override_reason']) . '"' : ''
                );
            }

            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="schedule_export_' . date('Y-m-d') . '.csv"');
        }

        return response()->json([
            'success' => true,
            'data' => $scheduleItems,
            'count' => $scheduleItems->count()
        ]);
    }
}
