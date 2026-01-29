<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ContestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('contests')
            ->when(Schema::hasColumn('contests', 'tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('end_at');
        $items = $q->get();
        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'visibility_scope' => 'nullable|in:all,group,invite',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $payload = [
            'title' => $request->title,
            'description' => $request->description,
            'start_at' => $request->start_at,
            'end_at' => $request->end_at,
            'visibility_scope' => $request->visibility_scope ?? 'all',
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('contests', 'tenant_id')) {
            $payload['tenant_id'] = $tenantId;
        }
        $id = DB::table('contests')->insertGetId($payload);
        $row = DB::table('contests')->where('id', $id)->first();
        return response()->json(['data' => $row], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $c = DB::table('contests')->where('id', $id)
            ->when(Schema::hasColumn('contests', 'tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        return response()->json(['data' => $c]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:65535',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date',
            'visibility_scope' => 'sometimes|in:all,group,invite',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $c = DB::table('contests')->where('id', $id)
            ->when(Schema::hasColumn('contests', 'tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        $upd = array_filter([
            'title' => $request->title,
            'description' => $request->description,
            'start_at' => $request->start_at,
            'end_at' => $request->end_at,
            'visibility_scope' => $request->visibility_scope,
        ], fn ($x) => $x !== null);
        $upd['updated_at'] = now();
        DB::table('contests')->where('id', $id)->update($upd);
        $row = DB::table('contests')->where('id', $id)->first();
        return response()->json(['data' => $row]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $c = DB::table('contests')->where('id', $id)
            ->when(Schema::hasColumn('contests', 'tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        DB::table('contests')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function setTargets(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'targets' => 'required|array|min:1',
            'targets.*.group_id' => 'nullable|exists:groups,id',
            'targets.*.user_id' => 'nullable|exists:users,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $c = $this->contest($id, $tenantId);
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        DB::table('contest_targets')->where('contest_id', $id)->delete();
        foreach ($request->targets as $t) {
            $gid = $t['group_id'] ?? null;
            $uid = $t['user_id'] ?? null;
            if (!$gid && !$uid) {
                continue;
            }
            DB::table('contest_targets')->insertOrIgnore([
                'contest_id' => $id,
                'group_id' => $gid,
                'user_id' => $uid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json(['message' => 'OK']);
    }

    public function addJury(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|in:chair,member',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $c = $this->contest($id, $tenantId);
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        DB::table('contest_jury')->insertOrIgnore([
            'contest_id' => $id,
            'user_id' => $request->user_id,
            'role' => $request->role ?? 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'OK']);
    }

    public function addRubric(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'criteria_json' => 'required|array',
            'criteria_json.*.key' => 'required|string|max:64',
            'criteria_json.*.title' => 'required|string|max:255',
            'criteria_json.*.maxScore' => 'required|numeric|min:0',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $c = $this->contest($id, $tenantId);
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        $criteria = array_map(fn ($c) => [
            'key' => $c['key'],
            'title' => $c['title'],
            'maxScore' => (float) $c['maxScore'],
            'weight' => $c['weight'] ?? 1,
        ], $request->criteria_json);
        DB::table('contest_rubrics')->insert([
            'contest_id' => $id,
            'title' => $request->title,
            'criteria_json' => json_encode($criteria),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'OK']);
    }

    public function submit(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:65535',
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'exists:files,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $userId = (int) auth()->id();
        $c = $this->contest($id, $tenantId);
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        $now = now();
        if ($now->lt($c->start_at) || $now->gt($c->end_at)) {
            return response()->json(['message' => 'Contest not open for submissions'], 422);
        }
        $sub = DB::table('contest_submissions')
            ->where('contest_id', $id)
            ->where('participant_user_id', $userId)
            ->first();
        if ($sub) {
            return response()->json(['message' => 'Already submitted'], 422);
        }
        $subId = DB::table('contest_submissions')->insertGetId([
            'contest_id' => $id,
            'participant_user_id' => $userId,
            'title' => $request->title,
            'description' => $request->description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ($request->file_ids ?? [] as $fid) {
            DB::table('contest_submission_files')->insertOrIgnore([
                'submission_id' => $subId,
                'file_id' => $fid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $row = DB::table('contest_submissions')->where('id', $subId)->first();
        return response()->json(['data' => $row], 201);
    }

    public function getSubmissions(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $c = $this->contest($id, $tenantId);
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        $items = DB::table('contest_submissions')->where('contest_id', $id)->orderBy('id')->get();
        return response()->json(['data' => $items]);
    }

    public function getSubmission(Request $request, $id, $sid): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $c = $this->contest($id, $tenantId);
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        $row = DB::table('contest_submissions')
            ->where('contest_id', $id)
            ->where('id', $sid)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Submission not found'], 404);
        }
        return response()->json(['data' => $row]);
    }

    public function score(Request $request, $id, $sid): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'rubric_json' => 'required|array',
            'comment' => 'nullable|string|max:65535',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $userId = (int) auth()->id();
        $c = $this->contest($id, $tenantId);
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        $sub = DB::table('contest_submissions')->where('contest_id', $id)->where('id', $sid)->first();
        if (!$sub) {
            return response()->json(['message' => 'Submission not found'], 404);
        }
        $jury = DB::table('contest_jury')->where('contest_id', $id)->where('user_id', $userId)->first();
        if (!$jury) {
            return response()->json(['message' => 'Not a jury member'], 403);
        }
        $rubric = $request->rubric_json;
        $total = array_sum(array_map('floatval', $rubric));
        $exists = DB::table('contest_scores')
            ->where('submission_id', $sid)
            ->where('jury_user_id', $userId)
            ->exists();
        $payload = [
            'contest_id' => $id,
            'rubric_json' => json_encode($rubric),
            'total_score' => $total,
            'comment' => $request->comment,
            'updated_at' => now(),
        ];
        if ($exists) {
            DB::table('contest_scores')
                ->where('submission_id', $sid)
                ->where('jury_user_id', $userId)
                ->update($payload);
        } else {
            $payload['submission_id'] = $sid;
            $payload['jury_user_id'] = $userId;
            $payload['created_at'] = now();
            DB::table('contest_scores')->insert($payload);
        }
        return response()->json(['message' => 'OK']);
    }

    public function getScores(Request $request, $id, $sid): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $c = $this->contest($id, $tenantId);
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        $sub = DB::table('contest_submissions')->where('contest_id', $id)->where('id', $sid)->first();
        if (!$sub) {
            return response()->json(['message' => 'Submission not found'], 404);
        }
        $items = DB::table('contest_scores')->where('submission_id', $sid)->get();
        return response()->json(['data' => $items]);
    }

    public function publishResults(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $c = $this->contest($id, $tenantId);
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        $subs = DB::table('contest_submissions')->where('contest_id', $id)->pluck('id');
        $scores = DB::table('contest_scores')->where('contest_id', $id)->get()->groupBy('submission_id');
        $now = now();
        $place = 0;
        $ranked = [];
        foreach ($subs as $sid) {
            $juryScores = $scores->get($sid, collect());
            $avg = $juryScores->isEmpty() ? 0 : $juryScores->avg('total_score');
            $ranked[] = ['submission_id' => $sid, 'final_score' => round($avg, 2)];
        }
        usort($ranked, fn ($a, $b) => $b['final_score'] <=> $a['final_score']);
        foreach ($ranked as $r) {
            $place++;
            $sid = $r['submission_id'];
            $exists = DB::table('contest_results')->where('contest_id', $id)->where('submission_id', $sid)->exists();
            if ($exists) {
                DB::table('contest_results')->where('contest_id', $id)->where('submission_id', $sid)->update([
                    'place' => $place,
                    'final_score' => $r['final_score'],
                    'published_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('contest_results')->insert([
                    'contest_id' => $id,
                    'submission_id' => $sid,
                    'place' => $place,
                    'final_score' => $r['final_score'],
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
        return response()->json(['message' => 'OK']);
    }

    public function getResults(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $c = $this->contest($id, $tenantId);
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }
        $items = DB::table('contest_results')->where('contest_id', $id)->orderBy('place')->get();
        return response()->json(['data' => $items]);
    }

    public function generateCertificates(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $c = $this->contest($id, $tenantId);
        if (!$c) {
            return response()->json(['message' => 'Contest not found'], 404);
        }

        $template = DB::table('doc_templates')
            ->where('type', 'certificate')
            ->when(Schema::hasColumn('doc_templates', 'tenant_id'), fn ($q) => $q->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            }))
            ->first();
        if (!$template) {
            $template = DB::table('doc_templates')->where('name', 'certificate_default')->first();
        }
        if (!$template) {
            return response()->json(['message' => 'Certificate template not found. Create a doc_template with type "certificate" or name "certificate_default".'], 404);
        }

        $results = DB::table('contest_results as r')
            ->join('contest_submissions as s', 's.id', '=', 'r.submission_id')
            ->leftJoin('users as u', 'u.id', '=', 's.participant_user_id')
            ->where('r.contest_id', $id)
            ->whereNotNull('r.published_at')
            ->orderBy('r.place')
            ->select('r.id as result_id', 'r.submission_id', 'r.place', 'r.final_score', 's.participant_user_id', 'u.fio')
            ->get();

        $certificates = [];
        $today = now()->format('Y-m-d');
        $userId = (int) auth()->id();

        foreach ($results as $r) {
            $fio = $r->fio ?? 'Участник #' . $r->participant_user_id;
            $num = 'CERT-' . $id . '-' . $r->submission_id . '-' . $r->result_id . '-' . substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
            $hash = hash('sha256', $num . '-' . now()->timestamp);

            $docId = DB::table('documents')->insertGetId([
                'type' => 'certificate',
                'number' => $num,
                'date' => $today,
                'status' => 'draft',
                'template_id' => $template->id,
                'data_json' => json_encode([
                    'fio' => $fio,
                    'contest' => $c->title ?? 'Конкурс',
                    'place' => $r->place,
                    'score' => $r->final_score,
                    'date' => now()->format('d.m.Y'),
                ]),
                'created_by' => $userId,
                'verify_hash' => $hash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $certificates[] = [
                'document_id' => (int) $docId,
                'submission_id' => $r->submission_id,
                'user_id' => $r->participant_user_id,
                'place' => $r->place,
            ];
        }

        return response()->json([
            'message' => count($certificates) ? 'Certificates created' : 'No results to certify',
            'contest_id' => (int) $id,
            'certificates' => $certificates,
        ]);
    }

    private function contest($id, int $tenantId): ?object
    {
        $q = DB::table('contests')->where('id', $id);
        if (Schema::hasColumn('contests', 'tenant_id')) {
            $q->where('tenant_id', $tenantId);
        }
        return $q->first();
    }
}
