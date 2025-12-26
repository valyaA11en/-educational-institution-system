<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CurriculumPlan;
use App\Models\CurriculumTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurriculumController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CurriculumPlan::with(['subject', 'group', 'term', 'teacher', 'topics']);

        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        if ($groupId = $request->query('group_id')) {
            $query->where('group_id', $groupId);
        }

        if ($termId = $request->query('term_id')) {
            $query->where('term_id', $termId);
        }

        if ($request->boolean('templates_only')) {
            $query->where('is_template', true);
        }

        $plans = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'teacher_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_template' => ['sometimes', 'boolean'],
            'template_id' => ['sometimes', 'nullable', 'integer', 'exists:curriculum_plans,id'],
        ]);

        $plan = CurriculumPlan::create($validated);

        return response()->json($plan->load(['subject', 'group', 'term', 'teacher']), 201);
    }

    public function show(int $id): JsonResponse
    {
        $plan = CurriculumPlan::with(['subject', 'group', 'term', 'teacher', 'topics.lessons', 'topics.assignments'])
            ->findOrFail($id);

        $plan->progress = $plan->progress();

        return response()->json($plan);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = CurriculumPlan::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'teacher_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $plan->update($validated);

        return response()->json($plan->load(['subject', 'group', 'term', 'teacher']));
    }

    public function destroy(int $id): JsonResponse
    {
        $plan = CurriculumPlan::findOrFail($id);
        $plan->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function copy(Request $request, int $id): JsonResponse
    {
        $sourcePlan = CurriculumPlan::with('topics')->findOrFail($id);

        $validated = $request->validate([
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'group_id' => ['sometimes', 'integer', 'exists:groups,id'],
            'term_id' => ['sometimes', 'integer', 'exists:terms,id'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        DB::beginTransaction();
        try {
            $newPlan = CurriculumPlan::create([
                'subject_id' => $validated['subject_id'] ?? $sourcePlan->subject_id,
                'group_id' => $validated['group_id'] ?? $sourcePlan->group_id,
                'term_id' => $validated['term_id'] ?? $sourcePlan->term_id,
                'teacher_user_id' => $sourcePlan->teacher_user_id,
                'name' => $validated['name'] ?? $sourcePlan->name . ' (копия)',
                'description' => $sourcePlan->description,
                'is_template' => false,
                'template_id' => $sourcePlan->id,
            ]);

            foreach ($sourcePlan->topics as $topic) {
                $newTopic = CurriculumTopic::create([
                    'curriculum_plan_id' => $newPlan->id,
                    'order' => $topic->order,
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'hours_total' => $topic->hours_total,
                    'hours_lecture' => $topic->hours_lecture,
                    'hours_practice' => $topic->hours_practice,
                    'hours_lab' => $topic->hours_lab,
                    'control_type' => $topic->control_type,
                ]);
            }

            DB::commit();
            return response()->json($newPlan->load(['subject', 'group', 'term', 'teacher', 'topics']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function addTopic(Request $request, int $id): JsonResponse
    {
        $plan = CurriculumPlan::findOrFail($id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'hours_total' => ['required', 'integer', 'min:0'],
            'hours_lecture' => ['sometimes', 'integer', 'min:0'],
            'hours_practice' => ['sometimes', 'integer', 'min:0'],
            'hours_lab' => ['sometimes', 'integer', 'min:0'],
            'control_type' => ['sometimes', 'nullable', 'string'],
            'order' => ['sometimes', 'integer'],
        ]);

        $maxOrder = $plan->topics()->max('order') ?? 0;
        $validated['order'] = $validated['order'] ?? $maxOrder + 1;
        $validated['curriculum_plan_id'] = $plan->id;

        $topic = CurriculumTopic::create($validated);

        return response()->json($topic, 201);
    }

    public function updateTopic(Request $request, int $id, int $topicId): JsonResponse
    {
        $topic = CurriculumTopic::where('curriculum_plan_id', $id)->findOrFail($topicId);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'hours_total' => ['sometimes', 'integer', 'min:0'],
            'hours_lecture' => ['sometimes', 'integer', 'min:0'],
            'hours_practice' => ['sometimes', 'integer', 'min:0'],
            'hours_lab' => ['sometimes', 'integer', 'min:0'],
            'control_type' => ['sometimes', 'nullable', 'string'],
            'order' => ['sometimes', 'integer'],
        ]);

        $topic->update($validated);

        return response()->json($topic);
    }

    public function deleteTopic(int $id, int $topicId): JsonResponse
    {
        $topic = CurriculumTopic::where('curriculum_plan_id', $id)->findOrFail($topicId);
        $topic->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function linkLesson(Request $request, int $id, int $topicId): JsonResponse
    {
        $topic = CurriculumTopic::where('curriculum_plan_id', $id)->findOrFail($topicId);

        $validated = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
        ]);

        $topic->lessons()->syncWithoutDetaching([$validated['lesson_id']]);

        return response()->json($topic->load('lessons'));
    }

    public function linkAssignment(Request $request, int $id, int $topicId): JsonResponse
    {
        $topic = CurriculumTopic::where('curriculum_plan_id', $id)->findOrFail($topicId);

        $validated = $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:assignments,id'],
        ]);

        $topic->assignments()->syncWithoutDetaching([$validated['assignment_id']]);

        return response()->json($topic->load('assignments'));
    }
}


