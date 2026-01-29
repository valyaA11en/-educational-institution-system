<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('materials')->where('tenant_id', $tenantId);

        if ($request->filled('subject_id')) {
            $q->where('subject_id', $request->subject_id);
        }
        if ($request->filled('created_by')) {
            $q->where('created_by', $request->created_by);
        }

        $items = $q->orderBy('id')->get();
        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'subject_id' => 'required|exists:subjects,id',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string|max:65535',
            'visibility_scope' => 'required|in:group,subgroup,individual,all',
            'targets' => 'required_unless:visibility_scope,all|array',
            'targets.*.group_id' => 'nullable|exists:groups,id',
            'targets.*.subgroup_id' => 'nullable|exists:subgroups,id',
            'targets.*.student_user_id' => 'nullable|exists:users,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $scope = $request->visibility_scope;
        $targets = $request->targets ?? [];

        if ($scope !== 'all') {
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
        }

        DB::beginTransaction();
        try {
            $id = DB::table('materials')->insertGetId([
                'tenant_id' => $tenantId,
                'subject_id' => $request->subject_id,
                'title' => $request->title,
                'content' => $request->content,
                'visibility_scope' => $scope,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($targets as $t) {
                DB::table('material_targets')->insert([
                    'material_id' => $id,
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
            return response()->json(['message' => 'Failed to create material: ' . $e->getMessage()], 500);
        }

        $row = DB::table('materials')->where('id', $id)->first();
        return response()->json(['data' => $row], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $m = DB::table('materials')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$m) {
            return response()->json(['message' => 'Material not found'], 404);
        }
        if (!$this->canAccess($id, $m->visibility_scope, (int) auth()->id())) {
            return response()->json(['message' => 'Access denied'], 403);
        }
        $targets = DB::table('material_targets')->where('material_id', $id)->get();
        $m->targets = $targets;
        return response()->json(['data' => $m]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'subject_id' => 'sometimes|exists:subjects,id',
            'title' => 'sometimes|string|max:255',
            'content' => 'nullable|string|max:65535',
            'visibility_scope' => 'sometimes|in:group,subgroup,individual,all',
            'targets' => 'sometimes|array',
            'targets.*.group_id' => 'nullable|exists:groups,id',
            'targets.*.subgroup_id' => 'nullable|exists:subgroups,id',
            'targets.*.student_user_id' => 'nullable|exists:users,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $m = DB::table('materials')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$m) {
            return response()->json(['message' => 'Material not found'], 404);
        }

        $upd = array_filter([
            'subject_id' => $request->subject_id,
            'title' => $request->title,
            'content' => $request->content,
            'visibility_scope' => $request->visibility_scope,
        ], fn ($v) => $v !== null);
        $upd['updated_at'] = now();
        DB::table('materials')->where('id', $id)->update($upd);

        if ($request->has('targets')) {
            $scope = $request->visibility_scope ?? $m->visibility_scope;
            if ($scope !== 'all') {
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
            }
            DB::table('material_targets')->where('material_id', $id)->delete();
            foreach ($request->targets as $t) {
                DB::table('material_targets')->insert([
                    'material_id' => (int) $id,
                    'group_id' => $t['group_id'] ?? null,
                    'subgroup_id' => $t['subgroup_id'] ?? null,
                    'student_user_id' => $t['student_user_id'] ?? null,
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $row = DB::table('materials')->where('id', $id)->first();
        return response()->json(['data' => $row]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $m = DB::table('materials')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$m) {
            return response()->json(['message' => 'Material not found'], 404);
        }
        DB::table('materials')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function read(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $m = DB::table('materials')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$m) {
            return response()->json(['message' => 'Material not found'], 404);
        }
        if (!$this->canAccess($id, $m->visibility_scope, (int) auth()->id())) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $userId = (int) auth()->id();
        $exists = DB::table('material_reads')->where('material_id', $id)->where('user_id', $userId)->exists();
        if (!$exists) {
            DB::table('material_reads')->insert([
                'material_id' => (int) $id,
                'user_id' => $userId,
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json(['message' => 'OK']);
    }

    public function download(Request $request, $id): StreamedResponse|JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $m = DB::table('materials')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$m) {
            return response()->json(['message' => 'Material not found'], 404);
        }
        if (!$this->canAccess($id, $m->visibility_scope, (int) auth()->id())) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $filename = str_replace(['/', '\\', '"'], '-', $m->title) ?: 'material';
        $filename .= '.txt';

        return response()->streamDownload(
            function () use ($m): void {
                echo $m->content ?? '';
            },
            $filename,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ],
            'attachment'
        );
    }

    private function canAccess(int $materialId, string $scope, int $userId): bool
    {
        if ($scope === 'all') {
            return true;
        }
        if ($scope === 'individual') {
            return DB::table('material_targets')
                ->where('material_id', $materialId)
                ->where('student_user_id', $userId)
                ->exists();
        }
        if ($scope === 'group') {
            $groupIds = DB::table('group_members')
                ->where('user_id', $userId)
                ->where('role_in_group', 'student')
                ->pluck('group_id');
            return DB::table('material_targets')
                ->where('material_id', $materialId)
                ->whereIn('group_id', $groupIds)
                ->exists();
        }
        if ($scope === 'subgroup') {
            $userGroupIds = DB::table('group_members')
                ->where('user_id', $userId)
                ->where('role_in_group', 'student')
                ->pluck('group_id');
            $targetSubgroupIds = DB::table('material_targets')
                ->where('material_id', $materialId)
                ->whereNotNull('subgroup_id')
                ->pluck('subgroup_id');
            $subgroupGroupIds = DB::table('subgroups')->whereIn('id', $targetSubgroupIds)->pluck('group_id');

            return $userGroupIds->intersect($subgroupGroupIds)->isNotEmpty();
        }
        return false;
    }
}
