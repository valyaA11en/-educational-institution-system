<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('assignments')->where('tenant_id', $tenantId);

        if ($request->filled('subject_id')) {
            $q->where('subject_id', $request->subject_id);
        }
        if ($request->filled('teacher_user_id')) {
            $q->where('teacher_user_id', $request->teacher_user_id);
        }
        if ($request->filled('group_id')) {
            $q->whereExists(function ($ex) use ($request): void {
                $ex->select(DB::raw(1))
                    ->from('assignment_targets')
                    ->whereColumn('assignment_targets.assignment_id', 'assignments.id')
                    ->where('assignment_targets.group_id', $request->group_id);
            });
        }

        $items = $q->orderBy('assignments.id')->get();
        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'subject_id' => 'required|exists:subjects,id',
            'teacher_user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'due_at' => 'nullable|date',
            'max_attempts' => 'nullable|integer|min:1',
            'max_file_size' => 'nullable|integer|min:0',
            'allowed_types' => 'nullable|array',
            'allowed_types.*' => 'string|max:64',
            'visibility_scope' => 'required|in:group,subgroup,individual',
            'targets' => 'required|array|min:1',
            'targets.*.group_id' => 'nullable|exists:groups,id',
            'targets.*.subgroup_id' => 'nullable|exists:subgroups,id',
            'targets.*.student_user_id' => 'nullable|exists:users,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $scope = $request->visibility_scope;
        $targets = $request->targets;

        foreach ($targets as $t) {
            if ($scope === 'group' && empty($t['group_id'])) {
                return response()->json(['message' => 'Group required for visibility_scope=group', 'errors' => ['targets' => ['Invalid.']]], 422);
            }
            if ($scope === 'subgroup' && empty($t['subgroup_id'])) {
                return response()->json(['message' => 'Subgroup required for visibility_scope=subgroup', 'errors' => ['targets' => ['Invalid.']]], 422);
            }
            if ($scope === 'individual' && empty($t['student_user_id'])) {
                return response()->json(['message' => 'Student required for visibility_scope=individual', 'errors' => ['targets' => ['Invalid.']]], 422);
            }
        }

        DB::beginTransaction();
        try {
            $id = DB::table('assignments')->insertGetId([
                'tenant_id' => $tenantId,
                'subject_id' => $request->subject_id,
                'teacher_user_id' => $request->teacher_user_id,
                'title' => $request->title,
                'description' => $request->description,
                'due_at' => $request->due_at,
                'max_attempts' => $request->max_attempts ?? 1,
                'max_file_size' => $request->max_file_size,
                'allowed_types' => $request->has('allowed_types') ? json_encode($request->allowed_types) : null,
                'visibility_scope' => $scope,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($targets as $t) {
                DB::table('assignment_targets')->insert([
                    'assignment_id' => $id,
                    'group_id' => $t['group_id'] ?? null,
                    'subgroup_id' => $t['subgroup_id'] ?? null,
                    'student_user_id' => $t['student_user_id'] ?? null,
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create assignment: ' . $e->getMessage()], 500);
        }

        $row = DB::table('assignments')->where('id', $id)->first();
        return response()->json(['data' => $row], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $a = DB::table('assignments')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$a) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }
        $targets = DB::table('assignment_targets')->where('assignment_id', $id)->get();
        $a->targets = $targets;
        return response()->json(['data' => $a]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'subject_id' => 'sometimes|exists:subjects,id',
            'teacher_user_id' => 'sometimes|exists:users,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:65535',
            'due_at' => 'nullable|date',
            'max_attempts' => 'nullable|integer|min:1',
            'max_file_size' => 'nullable|integer|min:0',
            'allowed_types' => 'nullable|array',
            'allowed_types.*' => 'string|max:64',
            'visibility_scope' => 'sometimes|in:group,subgroup,individual',
            'targets' => 'sometimes|array|min:1',
            'targets.*.group_id' => 'nullable|exists:groups,id',
            'targets.*.subgroup_id' => 'nullable|exists:subgroups,id',
            'targets.*.student_user_id' => 'nullable|exists:users,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $a = DB::table('assignments')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$a) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        $upd = array_filter([
            'subject_id' => $request->subject_id,
            'teacher_user_id' => $request->teacher_user_id,
            'title' => $request->title,
            'description' => $request->description,
            'due_at' => $request->due_at,
            'max_attempts' => $request->max_attempts,
            'max_file_size' => $request->max_file_size,
            'allowed_types' => $request->has('allowed_types') ? json_encode($request->allowed_types) : null,
            'visibility_scope' => $request->visibility_scope,
        ], fn ($v) => $v !== null);
        $upd['updated_at'] = now();

        DB::table('assignments')->where('id', $id)->update($upd);

        if ($request->has('targets')) {
            $scope = $request->visibility_scope ?? $a->visibility_scope;
            foreach ($request->targets as $t) {
                if ($scope === 'group' && empty($t['group_id'])) {
                    return response()->json(['message' => 'Group required for visibility_scope=group', 'errors' => ['targets' => ['Invalid.']]], 422);
                }
                if ($scope === 'subgroup' && empty($t['subgroup_id'])) {
                    return response()->json(['message' => 'Subgroup required for visibility_scope=subgroup', 'errors' => ['targets' => ['Invalid.']]], 422);
                }
                if ($scope === 'individual' && empty($t['student_user_id'])) {
                    return response()->json(['message' => 'Student required for visibility_scope=individual', 'errors' => ['targets' => ['Invalid.']]], 422);
                }
            }
            DB::table('assignment_targets')->where('assignment_id', $id)->delete();
            foreach ($request->targets as $t) {
                DB::table('assignment_targets')->insert([
                    'assignment_id' => (int) $id,
                    'group_id' => $t['group_id'] ?? null,
                    'subgroup_id' => $t['subgroup_id'] ?? null,
                    'student_user_id' => $t['student_user_id'] ?? null,
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $row = DB::table('assignments')->where('id', $id)->first();
        return response()->json(['data' => $row]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $a = DB::table('assignments')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$a) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }
        DB::table('assignments')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function submit(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'text' => 'nullable|string|max:65535',
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'exists:files,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $userId = (int) auth()->id();
        $a = DB::table('assignments')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$a) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        $canSubmit = $this->canSubmit($id, $userId, $a->visibility_scope);
        if (!$canSubmit) {
            return response()->json(['message' => 'You are not allowed to submit this assignment'], 403);
        }

        $sub = DB::table('submissions')
            ->where('assignment_id', $id)
            ->where('student_user_id', $userId)
            ->first();

        if ($sub) {
            if ($sub->status !== 'draft') {
                return response()->json(['message' => 'Already submitted'], 422);
            }
            DB::table('submissions')->where('id', $sub->id)->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'text' => $request->text ?? $sub->text,
                'updated_at' => now(),
            ]);
            $subId = $sub->id;
        } else {
            $subId = DB::table('submissions')->insertGetId([
                'assignment_id' => $id,
                'student_user_id' => $userId,
                'tenant_id' => $tenantId,
                'status' => 'submitted',
                'submitted_at' => now(),
                'text' => $request->text,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($request->filled('file_ids')) {
            foreach ($request->file_ids as $fid) {
                $exists = DB::table('submission_files')
                    ->where('submission_id', $subId)
                    ->where('file_id', $fid)
                    ->exists();
                if (!$exists) {
                    DB::table('submission_files')->insert([
                        'submission_id' => $subId,
                        'file_id' => $fid,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $row = DB::table('submissions')->where('id', $subId)->first();
        return response()->json(['data' => $row], 200);
    }

    public function grade(Request $request, $id, $submissionId): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'value' => 'required|integer|min:1|max:5',
            'weight' => 'nullable|integer|min:1',
            'comment' => 'nullable|string|max:65535',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $a = DB::table('assignments')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$a) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        $sub = DB::table('submissions')
            ->where('id', $submissionId)
            ->where('assignment_id', $id)
            ->first();
        if (!$sub) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        $studentId = (int) $sub->student_user_id;
        $existing = DB::table('grades')
            ->where('assignment_id', $id)
            ->where('student_user_id', $studentId)
            ->first();

        if ($existing) {
            DB::table('grades')->where('id', $existing->id)->update([
                'value' => $request->value,
                'weight' => $request->weight ?? 1,
                'comment' => $request->comment,
                'updated_at' => now(),
            ]);
            $gradeId = $existing->id;
        } else {
            $gradeId = DB::table('grades')->insertGetId([
                'tenant_id' => $tenantId,
                'lesson_id' => null,
                'assignment_id' => $id,
                'student_user_id' => $studentId,
                'value' => $request->value,
                'weight' => $request->weight ?? 1,
                'grade_type' => 'assignment',
                'comment' => $request->comment,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('submissions')->where('id', $submissionId)->update([
            'status' => 'graded',
            'updated_at' => now(),
        ]);

        $row = DB::table('grades')->where('id', $gradeId)->first();
        return response()->json(['data' => $row]);
    }

    private function canSubmit(int $assignmentId, int $studentUserId, string $scope): bool
    {
        if ($scope === 'individual') {
            return DB::table('assignment_targets')
                ->where('assignment_id', $assignmentId)
                ->where('student_user_id', $studentUserId)
                ->exists();
        }
        if ($scope === 'group') {
            $studentGroup = DB::table('group_members')
                ->where('user_id', $studentUserId)
                ->where('role_in_group', 'student')
                ->pluck('group_id');
            return DB::table('assignment_targets')
                ->where('assignment_id', $assignmentId)
                ->whereIn('group_id', $studentGroup)
                ->exists();
        }
        if ($scope === 'subgroup') {
            $studentGroupIds = DB::table('group_members')
                ->where('user_id', $studentUserId)
                ->where('role_in_group', 'student')
                ->pluck('group_id');
            $targetSubgroupIds = DB::table('assignment_targets')
                ->where('assignment_id', $assignmentId)
                ->whereNotNull('subgroup_id')
                ->pluck('subgroup_id');
            $subgroupGroupIds = DB::table('subgroups')->whereIn('id', $targetSubgroupIds)->pluck('group_id');
            return $studentGroupIds->intersect($subgroupGroupIds)->isNotEmpty();
        }
        return false;
    }
}
