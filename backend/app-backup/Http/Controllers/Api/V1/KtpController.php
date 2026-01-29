<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CurriculumPlan;
use App\Models\CurriculumTopic;
use App\Models\KtpTemplate;
use App\Models\KtpTopicLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KtpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CurriculumPlan::class);

        $query = CurriculumPlan::with(['subject', 'group', 'term', 'teacher', 'creator']);

        if ($termId = $request->query('termId')) {
            $query->where('term_id', $termId);
        }

        if ($groupId = $request->query('groupId')) {
            $query->where('group_id', $groupId);
        }

        if ($subjectId = $request->query('subjectId')) {
            $query->where('subject_id', $subjectId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $plans = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CurriculumPlan::class);

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'teacher_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:draft,active,archived'],
        ]);

        $validated['created_by'] = auth()->id();
        $validated['status'] = $validated['status'] ?? 'draft';
        $validated['name'] = $validated['name'] ?? 'КТП';

        $plan = CurriculumPlan::create($validated);

        return response()->json($plan->load(['subject', 'group', 'term', 'teacher', 'creator']), 201);
    }

    public function show(int $id): JsonResponse
    {
        $plan = CurriculumPlan::with([
            'subject',
            'group',
            'term',
            'teacher',
            'creator',
            'topics.links.lesson',
            'topics.links.assignment',
            'topics.links.material',
        ])
            ->findOrFail($id);

        $this->authorize('view', $plan);

        return response()->json($plan);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = CurriculumPlan::findOrFail($id);
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'teacher_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', 'in:draft,active,archived'],
        ]);

        $plan->update($validated);

        return response()->json($plan->load(['subject', 'group', 'term', 'teacher', 'creator']));
    }

    public function addTopics(Request $request, int $id): JsonResponse
    {
        $plan = CurriculumPlan::findOrFail($id);
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'topics' => ['required', 'array', 'min:1'],
            'topics.*.title' => ['required', 'string', 'max:255'],
            'topics.*.hours' => ['required', 'integer', 'min:0'],
            'topics.*.control_type' => ['sometimes', 'nullable', 'string'],
            'topics.*.planned_date_from' => ['sometimes', 'nullable', 'date'],
            'topics.*.planned_date_to' => ['sometimes', 'nullable', 'date'],
            'topics.*.order_no' => ['sometimes', 'integer'],
        ]);

        DB::beginTransaction();
        try {
            $maxOrder = $plan->topics()->max('order_no') ?? 0;
            $createdTopics = [];

            foreach ($validated['topics'] as $index => $topicData) {
                $topicData['curriculum_plan_id'] = $plan->id;
                $topicData['order_no'] = $topicData['order_no'] ?? ($maxOrder + $index + 1);
                $topicData['hours_total'] = $topicData['hours'];
                $topicData['order'] = $topicData['order_no']; // For backward compatibility

                $topic = CurriculumTopic::create($topicData);
                $createdTopics[] = $topic;
            }

            DB::commit();
            return response()->json(['data' => $createdTopics], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateTopic(Request $request, int $id): JsonResponse
    {
        $topic = CurriculumTopic::findOrFail($id);
        $plan = $topic->plan;
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'hours' => ['sometimes', 'integer', 'min:0'],
            'control_type' => ['sometimes', 'nullable', 'string'],
            'planned_date_from' => ['sometimes', 'nullable', 'date'],
            'planned_date_to' => ['sometimes', 'nullable', 'date'],
            'order_no' => ['sometimes', 'integer'],
        ]);

        if (isset($validated['hours'])) {
            $validated['hours_total'] = $validated['hours'];
        }
        if (isset($validated['order_no'])) {
            $validated['order'] = $validated['order_no'];
        }

        $topic->update($validated);

        return response()->json($topic);
    }

    public function deleteTopic(int $id): JsonResponse
    {
        $topic = CurriculumTopic::findOrFail($id);
        $plan = $topic->plan;
        $this->authorize('update', $plan);

        $topic->delete();

        return response()->json(['message' => 'Deleted'], 204);
    }

    public function linkTopic(Request $request, int $id): JsonResponse
    {
        $topic = CurriculumTopic::findOrFail($id);
        $plan = $topic->plan;
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'lessonId' => ['sometimes', 'nullable', 'integer', 'exists:lessons,id'],
            'assignmentId' => ['sometimes', 'nullable', 'integer', 'exists:assignments,id'],
            'materialId' => ['sometimes', 'nullable', 'integer', 'exists:materials,id'],
        ]);

        // Only one link type allowed
        $linkCount = array_filter([
            isset($validated['lessonId']),
            isset($validated['assignmentId']),
            isset($validated['materialId']),
        ]);

        if (count($linkCount) !== 1) {
            return response()->json(['message' => 'Exactly one link type must be provided'], 422);
        }

        $link = KtpTopicLink::create([
            'ktp_topic_id' => $topic->id,
            'lesson_id' => $validated['lessonId'] ?? null,
            'assignment_id' => $validated['assignmentId'] ?? null,
            'material_id' => $validated['materialId'] ?? null,
        ]);

        return response()->json($link->load(['lesson', 'assignment', 'material']), 201);
    }

    public function progress(int $id): JsonResponse
    {
        $plan = CurriculumPlan::with('topics')->findOrFail($id);
        $this->authorize('view', $plan);

        $topics = $plan->topics;
        $totalTopics = $topics->count();
        $linkedTopicsCount = 0;
        $alerts = [];

        foreach ($topics as $topic) {
            $hasLinks = KtpTopicLink::where('ktp_topic_id', $topic->id)->exists();
            if ($hasLinks) {
                $linkedTopicsCount++;
            }

            // Check for control topics without assignment/grade
            if ($topic->control_type === 'control') {
                $hasAssignment = KtpTopicLink::where('ktp_topic_id', $topic->id)
                    ->whereNotNull('assignment_id')
                    ->exists();

                // Check if assignment has grades (simplified)
                $hasGrade = false;
                if ($hasAssignment) {
                    $assignmentId = KtpTopicLink::where('ktp_topic_id', $topic->id)
                        ->whereNotNull('assignment_id')
                        ->value('assignment_id');
                    $hasGrade = DB::table('submissions')
                        ->where('assignment_id', $assignmentId)
                        ->whereNotNull('grade')
                        ->exists();
                }

                if (!$hasAssignment || !$hasGrade) {
                    $alerts[] = [
                        'topic_id' => $topic->id,
                        'topic_title' => $topic->title,
                        'message' => 'Тема с контролем не имеет задания или оценки',
                    ];
                }
            }
        }

        $percent = $totalTopics > 0 ? round(($linkedTopicsCount / $totalTopics) * 100, 2) : 0;

        return response()->json([
            'total_topics' => $totalTopics,
            'linked_topics_count' => $linkedTopicsCount,
            'percent' => $percent,
            'alerts' => $alerts,
        ]);
    }

    public function applyTemplate(Request $request, int $id): JsonResponse
    {
        $plan = CurriculumPlan::findOrFail($id);
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'templateId' => ['required', 'integer', 'exists:ktp_templates,id'],
        ]);

        $template = KtpTemplate::findOrFail($validated['templateId']);
        $templateData = $template->data_json ?? [];

        if (isset($templateData['topics']) && is_array($templateData['topics'])) {
            DB::beginTransaction();
            try {
                $maxOrder = $plan->topics()->max('order_no') ?? 0;

                foreach ($templateData['topics'] as $index => $topicData) {
                    CurriculumTopic::create([
                        'curriculum_plan_id' => $plan->id,
                        'order_no' => $maxOrder + $index + 1,
                        'order' => $maxOrder + $index + 1,
                        'title' => $topicData['title'] ?? 'Тема ' . ($index + 1),
                        'hours' => $topicData['hours'] ?? 0,
                        'hours_total' => $topicData['hours'] ?? 0,
                        'control_type' => $topicData['control_type'] ?? null,
                        'planned_date_from' => $topicData['planned_date_from'] ?? null,
                        'planned_date_to' => $topicData['planned_date_to'] ?? null,
                    ]);
                }

                DB::commit();
                return response()->json(['message' => 'Template applied successfully']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        return response()->json(['message' => 'Template has no topics'], 422);
    }

    public function copyFrom(Request $request, int $id): JsonResponse
    {
        $plan = CurriculumPlan::findOrFail($id);
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'sourcePlanId' => ['required', 'integer', 'exists:curriculum_plans,id'],
        ]);

        $sourcePlan = CurriculumPlan::with('topics')->findOrFail($validated['sourcePlanId']);

        DB::beginTransaction();
        try {
            $maxOrder = $plan->topics()->max('order_no') ?? 0;

            foreach ($sourcePlan->topics as $index => $sourceTopic) {
                CurriculumTopic::create([
                    'curriculum_plan_id' => $plan->id,
                    'order_no' => $maxOrder + $index + 1,
                    'order' => $maxOrder + $index + 1,
                    'title' => $sourceTopic->title,
                    'hours' => $sourceTopic->hours_total,
                    'hours_total' => $sourceTopic->hours_total,
                    'hours_lecture' => $sourceTopic->hours_lecture,
                    'hours_practice' => $sourceTopic->hours_practice,
                    'hours_lab' => $sourceTopic->hours_lab,
                    'control_type' => $sourceTopic->control_type,
                    'planned_date_from' => $sourceTopic->planned_date_from,
                    'planned_date_to' => $sourceTopic->planned_date_to,
                    'description' => $sourceTopic->description,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Topics copied successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

