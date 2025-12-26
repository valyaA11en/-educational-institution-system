<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamCommission;
use App\Models\ExamRegistration;
use App\Models\ExamResult;
use App\Models\ExamRule;
use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Support\Events\EventTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Exam::with(['subject', 'group', 'term', 'room', 'creator']);

        if ($termId = $request->query('termId')) {
            $query->where('term_id', $termId);
        }
        if ($groupId = $request->query('groupId')) {
            $query->where('group_id', $groupId);
        }
        if ($subjectId = $request->query('subjectId')) {
            $query->where('subject_id', $subjectId);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($from = $request->query('from')) {
            $query->where('date_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('date_at', '<=', $to);
        }

        $exams = $query->orderBy('date_at')->paginate($request->integer('per_page', 20));

        return response()->json($exams);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'type' => ['required', 'in:exam,test,attestation'],
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'date_at' => ['required', 'date'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);

        $exam = Exam::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        // Trigger webhook
        WebhookController::trigger(EventTypes::EXAM_CREATED, [
            'exam_id' => $exam->id,
            'title' => $exam->title,
            'date_at' => $exam->date_at,
        ]);

        return response()->json($exam->load(['subject', 'group', 'term', 'room', 'creator']), 201);
    }

    public function show(int $id): JsonResponse
    {
        $exam = Exam::with([
            'subject', 'group', 'term', 'room', 'creator',
            'commissions.user', 'registrations.student', 'results.student'
        ])->findOrFail($id);

        return response()->json($exam);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('update', $exam);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'date_at' => ['sometimes', 'date'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);

        $exam->update($validated);

        WebhookController::trigger(EventTypes::EXAM_UPDATED, [
            'exam_id' => $exam->id,
            'title' => $exam->title,
        ]);

        return response()->json($exam->load(['subject', 'group', 'term', 'room', 'creator']));
    }

    public function destroy(int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('delete', $exam);

        $exam->delete();

        return response()->json(['message' => 'Exam deleted']);
    }

    public function setCommission(Request $request, int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('update', $exam);

        $validated = $request->validate([
            'members' => ['required', 'array'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.role' => ['required', 'in:chair,member'],
        ]);

        // Remove existing commission
        ExamCommission::where('exam_id', $id)->delete();

        // Create new members
        $members = [];
        foreach ($validated['members'] as $member) {
            $members[] = ExamCommission::create([
                'exam_id' => $id,
                'user_id' => $member['user_id'],
                'role' => $member['role'],
            ])->load('user');
        }

        return response()->json(['members' => $members]);
    }

    public function getCommission(int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('view', $exam);

        $members = ExamCommission::where('exam_id', $id)
            ->with('user')
            ->get();

        return response()->json(['members' => $members]);
    }

    public function seedRegistrations(int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('update', $exam);

        if (!$exam->group_id) {
            return response()->json(['message' => 'Exam must have group_id'], 400);
        }

        $group = Group::findOrFail($exam->group_id);
        // Get students from group_members table
        $studentIds = DB::table('group_members')
            ->where('group_id', $exam->group_id)
            ->pluck('user_id');
        
        $students = User::whereIn('id', $studentIds)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'student');
            })
            ->get();

        $created = 0;
        foreach ($students as $student) {
            ExamRegistration::firstOrCreate(
                ['exam_id' => $id, 'student_user_id' => $student->id],
                ['status' => 'registered']
            );
            $created++;
        }

        return response()->json(['message' => "Created {$created} registrations"]);
    }

    public function evaluateAdmission(int $id, int $studentId): JsonResponse
    {
        $exam = Exam::with('term')->findOrFail($id);
        $this->authorize('update', $exam);

        $student = User::findOrFail($studentId);
        $registration = ExamRegistration::where('exam_id', $id)
            ->where('student_user_id', $studentId)
            ->firstOrFail();

        // Get rules
        $rule = ExamRule::where('term_id', $exam->term_id)->first();
        $config = $rule?->config_json ?? [
            'max_debts' => 3,
            'max_absences' => 20,
            'min_avg' => 3.0,
        ];

        // Calculate debts (TODO: from analytics or assignments)
        $debts = 0; // Placeholder
        // $debts = count of overdue assignments

        // Calculate absences (last 30 days or term)
        $absences = DB::table('attendance')
            ->where('user_id', $studentId)
            ->where('status', 'absent')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // Calculate average grade for term
        $avgGrade = DB::table('grades')
            ->where('user_id', $studentId)
            ->where('term_id', $exam->term_id)
            ->avg('value') ?? 0;

        $status = 'admitted';
        $reasons = [];

        if ($debts > ($config['max_debts'] ?? 3)) {
            $status = 'not_admitted';
            $reasons[] = "Превышено количество долгов: {$debts}";
        }

        if ($absences > ($config['max_absences'] ?? 20)) {
            $status = 'not_admitted';
            $reasons[] = "Превышено количество пропусков: {$absences}";
        }

        if ($avgGrade < ($config['min_avg'] ?? 3.0)) {
            $status = 'not_admitted';
            $reasons[] = "Средний балл ниже порога: {$avgGrade}";
        }

        $registration->update([
            'status' => $status,
            'reason' => !empty($reasons) ? implode('; ', $reasons) : null,
        ]);

        return response()->json([
            'status' => $status,
            'reason' => $registration->reason,
            'metrics' => [
                'debts' => $debts,
                'absences' => $absences,
                'avg_grade' => round($avgGrade, 2),
            ],
        ]);
    }

    public function admissionReport(int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('view', $exam);

        $registrations = ExamRegistration::where('exam_id', $id)
            ->with('student')
            ->get()
            ->map(function ($reg) {
                return [
                    'student_id' => $reg->student_user_id,
                    'student_fio' => $reg->student->fio ?? '',
                    'status' => $reg->status,
                    'reason' => $reg->reason,
                ];
            });

        return response()->json(['registrations' => $registrations]);
    }

    public function setResult(Request $request, int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('updateResult', $exam);

        $validated = $request->validate([
            'student_user_id' => ['required', 'integer', 'exists:users,id'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grade_value' => ['nullable', 'integer', 'min:2', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        $result = ExamResult::updateOrCreate(
            ['exam_id' => $id, 'student_user_id' => $validated['student_user_id']],
            [
                'score' => $validated['score'] ?? null,
                'grade_value' => $validated['grade_value'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'created_by' => auth()->id(),
            ]
        );

        return response()->json($result->load(['student', 'creator']));
    }

    public function getResults(int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('view', $exam);

        $results = ExamResult::where('exam_id', $id)
            ->with(['student', 'creator'])
            ->get();

        return response()->json(['results' => $results]);
    }

    public function getRules(int $termId): JsonResponse
    {
        $rule = ExamRule::where('term_id', $termId)->first();

        return response()->json($rule ?? ['term_id' => $termId, 'config_json' => []]);
    }

    public function setRules(Request $request, int $termId): JsonResponse
    {
        $this->authorize('exams.manage');

        $validated = $request->validate([
            'config_json' => ['required', 'array'],
            'config_json.max_debts' => ['nullable', 'integer', 'min:0'],
            'config_json.max_absences' => ['nullable', 'integer', 'min:0'],
            'config_json.min_avg' => ['nullable', 'numeric', 'min:0', 'max:5'],
        ]);

        $rule = ExamRule::updateOrCreate(
            ['term_id' => $termId],
            ['config_json' => $validated['config_json']]
        );

        return response()->json($rule);
    }

    public function generateSheet(int $id): JsonResponse
    {
        $exam = Exam::with([
            'subject', 'group', 'term', 'room', 'creator',
            'commissions.user', 'registrations.student', 'results.student'
        ])->findOrFail($id);

        $this->authorize('update', $exam);

        // TODO: Get template for grade_sheet
        $template = null; // DocumentTemplate::where('type', 'grade_sheet')->first();

        $dataJson = [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'type' => $exam->type,
                'date_at' => $exam->date_at,
                'subject' => $exam->subject?->name,
                'group' => $exam->group?->name,
                'room' => $exam->room?->name,
            ],
            'commission' => $exam->commissions->map(function ($c) {
                return [
                    'user_id' => $c->user_id,
                    'fio' => $c->user->fio ?? '',
                    'role' => $c->role,
                ];
            }),
            'students' => $exam->registrations->map(function ($reg) use ($exam) {
                $result = $exam->results->firstWhere('student_user_id', $reg->student_user_id);
                return [
                    'student_id' => $reg->student_user_id,
                    'fio' => $reg->student->fio ?? '',
                    'status' => $reg->status,
                    'score' => $result?->score,
                    'grade_value' => $result?->grade_value,
                    'comment' => $result?->comment,
                ];
            }),
        ];

        $document = Document::create([
            'template_id' => $template?->id,
            'type' => 'grade_sheet',
            'title' => "Ведомость: {$exam->title}",
            'data_json' => $dataJson,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return response()->json(['document_id' => $document->id, 'document' => $document]);
    }
}

