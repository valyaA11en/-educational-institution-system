<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestJury;
use App\Models\ContestResult;
use App\Models\ContestRubric;
use App\Models\ContestScore;
use App\Models\ContestSubmission;
use App\Models\ContestTarget;
use App\Models\File;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Events\EventTypes;

class ContestController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}
    public function index(Request $request): JsonResponse
    {
        $query = Contest::with(['creator', 'targets']);

        $user = auth()->user();

        // Filter by visibility_scope
        $query->where(function ($q) use ($user) {
            $q->where('visibility_scope', 'all')
                ->orWhere(function ($q2) use ($user) {
                    $q2->where('visibility_scope', 'group')
                        ->whereHas('targets', function ($tq) use ($user) {
                            $tq->where('group_id', $user->group_id);
                        });
                })
                ->orWhere(function ($q3) use ($user) {
                    $q3->where('visibility_scope', 'invite')
                        ->whereHas('targets', function ($tq) use ($user) {
                            $tq->where('user_id', $user->id);
                        });
                });
        });

        if ($visibility = $request->query('visibility_scope')) {
            $query->where('visibility_scope', $visibility);
        }

        // Filter by active
        if ($request->boolean('active')) {
            $now = now();
            $query->where('start_at', '<=', $now)
                  ->where('end_at', '>=', $now);
        }

        $contests = $query->orderBy('start_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($contests);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'visibility_scope' => ['required', 'in:all,group,invite'],
            'targets' => ['nullable', 'array'],
            'targets.*.group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'targets.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $contest = Contest::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'visibility_scope' => $validated['visibility_scope'],
            'created_by' => auth()->id(),
        ]);

        // Create targets
        if (!empty($validated['targets'])) {
            foreach ($validated['targets'] as $target) {
                ContestTarget::create([
                    'contest_id' => $contest->id,
                    'group_id' => $target['group_id'] ?? null,
                    'user_id' => $target['user_id'] ?? null,
                ]);
            }
        }

        WebhookController::trigger(EventTypes::CONTEST_CREATED, [
            'contest_id' => $contest->id,
            'title' => $contest->title,
        ]);

        return response()->json($contest->load(['creator', 'targets']), 201);
    }

    public function show(int $id): JsonResponse
    {
        $contest = Contest::with([
            'creator', 'targets.group', 'targets.user',
            'submissions.participant', 'submissions.files',
            'jury.user', 'rubrics', 'results.submission'
        ])->findOrFail($id);

        $this->authorize('view', $contest);

        return response()->json($contest);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('update', $contest);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date'],
            'visibility_scope' => ['sometimes', 'in:all,group,invite'],
        ]);

        $contest->update($validated);

        WebhookController::trigger(EventTypes::CONTEST_UPDATED, [
            'contest_id' => $contest->id,
            'title' => $contest->title,
        ]);

        return response()->json($contest->load(['creator', 'targets']));
    }

    public function destroy(int $id): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('delete', $contest);

        $contest->delete();

        return response()->json(['message' => 'Contest deleted']);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('submit', $contest);

        // Check if contest is active
        if (now() < $contest->start_at || now() > $contest->end_at) {
            return response()->json(['message' => 'Contest is not active'], 400);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fileIds' => ['required', 'array'],
            'fileIds.*' => ['integer', 'exists:files,id'],
        ]);

        $submission = ContestSubmission::create([
            'contest_id' => $id,
            'participant_user_id' => auth()->id(),
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        // Attach files
        foreach ($validated['fileIds'] as $fileId) {
            DB::table('contest_submission_files')->insert([
                'submission_id' => $submission->id,
                'file_id' => $fileId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        WebhookController::trigger(EventTypes::CONTEST_SUBMISSION, [
            'contest_id' => $id,
            'submission_id' => $submission->id,
            'user_id' => auth()->id(),
        ]);

        return response()->json($submission->load(['participant', 'files']), 201);
    }

    public function addJury(Request $request, int $id): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('update', $contest);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'in:chair,member'],
        ]);

        $jury = ContestJury::updateOrCreate(
            ['contest_id' => $id, 'user_id' => $validated['user_id']],
            ['role' => $validated['role']]
        );

        return response()->json($jury->load('user'));
    }

    public function addRubric(Request $request, int $id): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('update', $contest);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'criteria_json' => ['required', 'array'],
            'criteria_json.*.key' => ['required', 'string'],
            'criteria_json.*.title' => ['required', 'string'],
            'criteria_json.*.maxScore' => ['required', 'numeric', 'min:0'],
            'criteria_json.*.weight' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $rubric = ContestRubric::create([
            'contest_id' => $id,
            'title' => $validated['title'],
            'criteria_json' => $validated['criteria_json'],
        ]);

        return response()->json($rubric, 201);
    }

    public function score(Request $request, int $id, int $submissionId): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $submission = ContestSubmission::where('contest_id', $id)->findOrFail($submissionId);
        $this->authorize('score', $contest);

        $validated = $request->validate([
            'rubric_json' => ['required', 'array'],
            'comment' => ['nullable', 'string'],
        ]);

        // Calculate total score from rubric_json
        $rubric = ContestRubric::where('contest_id', $id)->first();
        if (!$rubric) {
            return response()->json(['message' => 'Rubric not found'], 404);
        }

        $totalScore = 0;
        foreach ($rubric->criteria_json as $criterion) {
            $key = $criterion['key'];
            $score = $validated['rubric_json'][$key] ?? 0;
            $weight = $criterion['weight'] ?? 1;
            $totalScore += ($score / $criterion['maxScore']) * 100 * $weight;
        }

        $score = ContestScore::updateOrCreate(
            [
                'contest_id' => $id,
                'submission_id' => $submissionId,
                'jury_user_id' => auth()->id(),
            ],
            [
                'rubric_json' => $validated['rubric_json'],
                'total_score' => $totalScore,
                'comment' => $validated['comment'] ?? null,
            ]
        );

        return response()->json($score->load('juryUser'));
    }

    private function calculateResults(int $id): void
    {
        $contest = Contest::findOrFail($id);

        $submissions = ContestSubmission::where('contest_id', $id)
            ->with('scores')
            ->get();

        DB::transaction(function () use ($contest, $submissions) {
            foreach ($submissions as $submission) {
                // Calculate average total_score from all jury scores
                $avgScore = ContestScore::where('submission_id', $submission->id)
                    ->avg('total_score') ?? 0;

                ContestResult::updateOrCreate(
                    ['contest_id' => $contest->id, 'submission_id' => $submission->id],
                    [
                        'final_score' => $avgScore,
                    ]
                );
            }

            // Calculate places
            $results = ContestResult::where('contest_id', $contest->id)
                ->orderBy('final_score', 'desc')
                ->get();

            $place = 1;
            foreach ($results as $result) {
                $result->update(['place' => $place++]);
            }
        });
    }

    public function setTargets(Request $request, int $id): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('update', $contest);

        $validated = $request->validate([
            'targets' => ['required', 'array'],
            'targets.*.group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'targets.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Delete existing targets
        ContestTarget::where('contest_id', $id)->delete();

        // Create new targets
        foreach ($validated['targets'] as $target) {
            ContestTarget::create([
                'contest_id' => $id,
                'group_id' => $target['group_id'] ?? null,
                'user_id' => $target['user_id'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Targets updated']);
    }

    public function getSubmissions(int $id): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('view', $contest);

        $submissions = ContestSubmission::where('contest_id', $id)
            ->with(['participant', 'files', 'scores.juryUser', 'result'])
            ->get();

        return response()->json($submissions);
    }

    public function getSubmission(int $id, int $sid): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('view', $contest);

        $submission = ContestSubmission::where('contest_id', $id)
            ->with(['participant', 'files', 'scores.juryUser', 'result'])
            ->findOrFail($sid);

        return response()->json($submission);
    }

    public function getScores(int $id, int $sid): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('view', $contest);

        $scores = ContestScore::where('contest_id', $id)
            ->where('submission_id', $sid)
            ->with('juryUser')
            ->get();

        return response()->json($scores);
    }

    public function publishResults(int $id): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('update', $contest);

        // First calculate results
        $this->calculateResults($id);

        // Then publish
        ContestResult::where('contest_id', $id)
            ->whereNull('published_at')
            ->update(['published_at' => now()]);

        // Notify participants
        $results = ContestResult::where('contest_id', $id)
            ->with('submission.participant')
            ->get();

        foreach ($results as $result) {
            if ($result->submission && $result->submission->participant) {
                $this->notificationService->create(
                    $result->submission->participant->id,
                    'contest.results_published',
                    [
                        'title' => 'Результаты конкурса опубликованы',
                        'body' => "Конкурс '{$contest->title}': место {$result->place}, балл {$result->final_score}",
                        'contest_id' => $id,
                        'url' => "/contests/{$id}/results",
                    ],
                    'in_app'
                );
            }
        }

        WebhookController::trigger(EventTypes::CONTEST_RESULTS_PUBLISHED, [
            'contest_id' => $id,
        ]);

        return response()->json(['message' => 'Results published']);
    }

    public function getResults(int $id): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('view', $contest);

        $results = ContestResult::where('contest_id', $id)
            ->with(['submission.participant'])
            ->orderBy('place')
            ->get();

        return response()->json($results);
    }

    public function generateCertificates(int $id): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $this->authorize('update', $contest);

        $results = ContestResult::where('contest_id', $id)
            ->whereNotNull('published_at')
            ->with(['submission.participant'])
            ->get();

        $template = \App\Models\DocTemplate::where('name', 'certificate_default')->first();
        if (!$template) {
            return response()->json(['message' => 'Certificate template not found'], 404);
        }

        $certificates = [];

        foreach ($results as $result) {
            if (!$result->submission || !$result->submission->participant) {
                continue;
            }

            $participant = $result->submission->participant;

            $document = \App\Models\Document::create([
                'template_id' => $template->id,
                'type' => 'certificate',
                'number' => 'CERT-' . $contest->id . '-' . $result->submission_id . '-' . time(),
                'status' => 'draft',
                'data_json' => [
                    'fio' => $participant->fio ?? $participant->name,
                    'contest' => $contest->title,
                    'place' => $result->place,
                    'score' => $result->final_score,
                    'date' => now()->format('d.m.Y'),
                ],
                'created_by' => auth()->id(),
                'date' => now(),
                'verify_hash' => hash('sha256', 'cert-' . $contest->id . '-' . $result->submission_id . '-' . time()),
            ]);

            $certificates[] = [
                'userId' => $participant->id,
                'documentId' => $document->id,
            ];
        }

        return response()->json($certificates);
    }

    public function generateCertificate(int $id, int $resultId): JsonResponse
    {
        $contest = Contest::findOrFail($id);
        $result = ContestResult::where('contest_id', $id)->findOrFail($resultId);

        // TODO: Generate PDF/DOCX certificate
        // Use template and generate file, save to files table

        return response()->json(['message' => 'Certificate generation not implemented']);
    }
}

