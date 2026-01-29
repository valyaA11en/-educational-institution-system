<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class CurriculumController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('curriculum_plans')->when(
            Schema::hasColumn('curriculum_plans', 'tenant_id'),
            fn ($q) => $q->where('tenant_id', $tenantId)
        )->orderBy('id');
        if ($request->filled('subject_id')) {
            $q->where('subject_id', $request->subject_id);
        }
        if ($request->filled('group_id')) {
            $q->where('group_id', $request->group_id);
        }
        if ($request->filled('term_id')) {
            $q->where('term_id', $request->term_id);
        }
        $items = $q->get();
        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'subject_id' => 'required|exists:subjects,id',
            'group_id' => 'required|exists:groups,id',
            'term_id' => 'required|exists:terms,id',
            'teacher_user_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'is_template' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $payload = [
            'subject_id' => $request->subject_id,
            'group_id' => $request->group_id,
            'term_id' => $request->term_id,
            'teacher_user_id' => $request->teacher_user_id,
            'name' => $request->name,
            'description' => $request->description,
            'is_template' => (int) $request->boolean('is_template', false),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('curriculum_plans', 'tenant_id')) {
            $payload['tenant_id'] = $tenantId;
        }
        $id = DB::table('curriculum_plans')->insertGetId($payload);
        $row = DB::table('curriculum_plans')->where('id', $id)->first();
        return response()->json(['data' => $row], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('curriculum_plans')->where('id', $id);
        if (Schema::hasColumn('curriculum_plans', 'tenant_id')) {
            $q->where('tenant_id', $tenantId);
        }
        $row = $q->first();
        if (!$row) {
            return response()->json(['message' => 'Not found'], 404);
        }
        return response()->json(['data' => $row]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'subject_id' => 'sometimes|exists:subjects,id',
            'group_id' => 'sometimes|exists:groups,id',
            'term_id' => 'sometimes|exists:terms,id',
            'teacher_user_id' => 'nullable|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:65535',
            'is_template' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('curriculum_plans')->where('id', $id);
        if (Schema::hasColumn('curriculum_plans', 'tenant_id')) {
            $q->where('tenant_id', $tenantId);
        }
        $plan = $q->first();
        if (!$plan) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $upd = array_filter([
            'subject_id' => $request->subject_id,
            'group_id' => $request->group_id,
            'term_id' => $request->term_id,
            'teacher_user_id' => $request->teacher_user_id,
            'name' => $request->name,
            'description' => $request->description,
            'is_template' => $request->has('is_template') ? (int) $request->boolean('is_template') : null,
        ], fn ($x) => $x !== null);
        $upd['updated_at'] = now();
        DB::table('curriculum_plans')->where('id', $id)->update($upd);
        return response()->json(['data' => DB::table('curriculum_plans')->where('id', $id)->first()]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('curriculum_plans')->where('id', $id);
        if (Schema::hasColumn('curriculum_plans', 'tenant_id')) {
            $q->where('tenant_id', $tenantId);
        }
        if (!$q->first()) {
            return response()->json(['message' => 'Not found'], 404);
        }
        DB::table('curriculum_plans')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function copy(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('curriculum_plans')->where('id', $id);
        if (Schema::hasColumn('curriculum_plans', 'tenant_id')) {
            $q->where('tenant_id', $tenantId);
        }
        $src = $q->first();
        if (!$src) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $payload = [
            'subject_id' => $src->subject_id,
            'group_id' => $src->group_id,
            'term_id' => $src->term_id,
            'teacher_user_id' => $src->teacher_user_id,
            'name' => ($src->name ?? 'Plan') . ' (copy)',
            'description' => $src->description,
            'is_template' => (int) ($src->is_template ?? 0),
            'template_id' => $src->template_id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('curriculum_plans', 'tenant_id')) {
            $payload['tenant_id'] = $tenantId;
        }
        $newId = DB::table('curriculum_plans')->insertGetId($payload);
        $topics = DB::table('curriculum_topics')->where('curriculum_plan_id', $id)->orderBy('order')->get();
        foreach ($topics as $t) {
            DB::table('curriculum_topics')->insert([
                'curriculum_plan_id' => $newId,
                'order' => $t->order,
                'title' => $t->title,
                'description' => $t->description,
                'hours_total' => $t->hours_total,
                'hours_lecture' => $t->hours_lecture ?? 0,
                'hours_practice' => $t->hours_practice ?? 0,
                'hours_lab' => $t->hours_lab ?? 0,
                'control_type' => $t->control_type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $row = DB::table('curriculum_plans')->where('id', $newId)->first();
        return response()->json(['data' => $row], 201);
    }

    public function addTopic(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'order' => 'required|integer|min:0',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'hours_total' => 'required|integer|min:0',
            'hours_lecture' => 'nullable|integer|min:0',
            'hours_practice' => 'nullable|integer|min:0',
            'hours_lab' => 'nullable|integer|min:0',
            'control_type' => 'nullable|string|max:64',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $plan = $this->plan($id, $tenantId);
        if (!$plan) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $topicId = DB::table('curriculum_topics')->insertGetId([
            'curriculum_plan_id' => $id,
            'order' => $request->order,
            'title' => $request->title,
            'description' => $request->description,
            'hours_total' => $request->hours_total,
            'hours_lecture' => $request->hours_lecture ?? 0,
            'hours_practice' => $request->hours_practice ?? 0,
            'hours_lab' => $request->hours_lab ?? 0,
            'control_type' => $request->control_type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = DB::table('curriculum_topics')->where('id', $topicId)->first();
        return response()->json(['data' => $row], 201);
    }

    public function updateTopic(Request $request, $id, $topicId): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'order' => 'sometimes|integer|min:0',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:65535',
            'hours_total' => 'sometimes|integer|min:0',
            'hours_lecture' => 'nullable|integer|min:0',
            'hours_practice' => 'nullable|integer|min:0',
            'hours_lab' => 'nullable|integer|min:0',
            'control_type' => 'nullable|string|max:64',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $plan = $this->plan($id, $tenantId);
        if (!$plan) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $topic = DB::table('curriculum_topics')->where('curriculum_plan_id', $id)->where('id', $topicId)->first();
        if (!$topic) {
            return response()->json(['message' => 'Topic not found'], 404);
        }
        $upd = array_filter([
            'order' => $request->order,
            'title' => $request->title,
            'description' => $request->description,
            'hours_total' => $request->hours_total,
            'hours_lecture' => $request->hours_lecture,
            'hours_practice' => $request->hours_practice,
            'hours_lab' => $request->hours_lab,
            'control_type' => $request->control_type,
        ], fn ($x) => $x !== null);
        $upd['updated_at'] = now();
        DB::table('curriculum_topics')->where('id', $topicId)->update($upd);
        return response()->json(['data' => DB::table('curriculum_topics')->where('id', $topicId)->first()]);
    }

    public function deleteTopic(Request $request, $id, $topicId): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $plan = $this->plan($id, $tenantId);
        if (!$plan) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $topic = DB::table('curriculum_topics')->where('curriculum_plan_id', $id)->where('id', $topicId)->first();
        if (!$topic) {
            return response()->json(['message' => 'Topic not found'], 404);
        }
        DB::table('curriculum_topics')->where('id', $topicId)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function linkLesson(Request $request, $id, $topicId): JsonResponse
    {
        $v = Validator::make($request->all(), ['lesson_id' => 'required|exists:lessons,id']);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $plan = $this->plan($id, $tenantId);
        if (!$plan) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $topic = DB::table('curriculum_topics')->where('curriculum_plan_id', $id)->where('id', $topicId)->first();
        if (!$topic) {
            return response()->json(['message' => 'Topic not found'], 404);
        }
        DB::table('curriculum_topic_lessons')->insertOrIgnore([
            'topic_id' => $topicId,
            'lesson_id' => $request->lesson_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'OK']);
    }

    public function linkAssignment(Request $request, $id, $topicId): JsonResponse
    {
        $v = Validator::make($request->all(), ['assignment_id' => 'required|exists:assignments,id']);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $plan = $this->plan($id, $tenantId);
        if (!$plan) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $topic = DB::table('curriculum_topics')->where('curriculum_plan_id', $id)->where('id', $topicId)->first();
        if (!$topic) {
            return response()->json(['message' => 'Topic not found'], 404);
        }
        DB::table('curriculum_topic_assignments')->insertOrIgnore([
            'topic_id' => $topicId,
            'assignment_id' => $request->assignment_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'OK']);
    }

    private function plan($id, int $tenantId): ?object
    {
        $q = DB::table('curriculum_plans')->where('id', $id);
        if (Schema::hasColumn('curriculum_plans', 'tenant_id')) {
            $q->where('tenant_id', $tenantId);
        }
        return $q->first();
    }
}
