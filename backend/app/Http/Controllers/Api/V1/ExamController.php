<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ExamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('exams')
            ->when(Schema::hasColumn('exams', 'tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('date_at');
        $items = $q->get();
        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'term_id' => 'required|exists:terms,id',
            'type' => 'required|in:exam,test,attestation',
            'title' => 'required|string|max:255',
            'subject_id' => 'nullable|exists:subjects,id',
            'group_id' => 'nullable|exists:groups,id',
            'date_at' => 'required|date',
            'room_id' => 'nullable|exists:rooms,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $payload = [
            'term_id' => $request->term_id,
            'type' => $request->type,
            'title' => $request->title,
            'subject_id' => $request->subject_id,
            'group_id' => $request->group_id,
            'date_at' => $request->date_at,
            'room_id' => $request->room_id,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('exams', 'tenant_id')) {
            $payload['tenant_id'] = (int) auth()->user()->tenant_id;
        }
        $id = DB::table('exams')->insertGetId($payload);
        $row = DB::table('exams')->where('id', $id)->first();
        return response()->json(['data' => $row], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $e = $this->exam($id, $tenantId);
        if (!$e) {
            return response()->json(['message' => 'Exam not found'], 404);
        }
        return response()->json(['data' => $e]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'term_id' => 'sometimes|exists:terms,id',
            'type' => 'sometimes|in:exam,test,attestation',
            'title' => 'sometimes|string|max:255',
            'subject_id' => 'nullable|exists:subjects,id',
            'group_id' => 'nullable|exists:groups,id',
            'date_at' => 'sometimes|date',
            'room_id' => 'nullable|exists:rooms,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $e = $this->exam($id, $tenantId);
        if (!$e) {
            return response()->json(['message' => 'Exam not found'], 404);
        }
        $upd = array_filter([
            'term_id' => $request->term_id,
            'type' => $request->type,
            'title' => $request->title,
            'subject_id' => $request->subject_id,
            'group_id' => $request->group_id,
            'date_at' => $request->date_at,
            'room_id' => $request->room_id,
        ], fn ($x) => $x !== null);
        $upd['updated_at'] = now();
        DB::table('exams')->where('id', $id)->update($upd);
        return response()->json(['data' => DB::table('exams')->where('id', $id)->first()]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $e = $this->exam($id, $tenantId);
        if (!$e) {
            return response()->json(['message' => 'Exam not found'], 404);
        }
        DB::table('exams')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function setCommission(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'members' => 'required|array|min:1',
            'members.*.user_id' => 'required|exists:users,id',
            'members.*.role' => 'required|in:chair,member',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $e = $this->exam($id, $tenantId);
        if (!$e) {
            return response()->json(['message' => 'Exam not found'], 404);
        }
        DB::table('exam_commissions')->where('exam_id', $id)->delete();
        foreach ($request->members as $m) {
            DB::table('exam_commissions')->insert([
                'exam_id' => $id,
                'user_id' => $m['user_id'],
                'role' => $m['role'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json(['message' => 'OK']);
    }

    public function getCommission(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $e = $this->exam($id, $tenantId);
        if (!$e) {
            return response()->json(['message' => 'Exam not found'], 404);
        }
        $items = DB::table('exam_commissions')->where('exam_id', $id)->get();
        return response()->json(['data' => $items]);
    }

    public function seedRegistrations(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $e = $this->exam($id, $tenantId);
        if (!$e) {
            return response()->json(['message' => 'Exam not found'], 404);
        }
        if (!$e->group_id) {
            return response()->json(['message' => 'Exam has no group'], 422);
        }
        $students = DB::table('group_members')
            ->where('group_id', $e->group_id)
            ->where('role_in_group', 'student')
            ->pluck('user_id');
        $created = 0;
        foreach ($students as $uid) {
            $ins = DB::table('exam_registrations')->insertOrIgnore([
                'exam_id' => $id,
                'student_user_id' => $uid,
                'status' => 'registered',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($ins) {
                $created++;
            }
            DB::table('exam_admissions')->insertOrIgnore([
                'exam_id' => $id,
                'user_id' => $uid,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json(['message' => "Seeded {$created} registrations", 'created' => $created]);
    }

    public function evaluateAdmission(Request $request, $id, $studentId): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'status' => 'required|in:admitted,not_admitted',
            'reason' => 'nullable|string|max:65535',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $userId = (int) auth()->id();
        $e = $this->exam($id, $tenantId);
        if (!$e) {
            return response()->json(['message' => 'Exam not found'], 404);
        }
        $n = DB::table('exam_admissions')
            ->where('exam_id', $id)
            ->where('user_id', $studentId)
            ->update([
                'status' => $request->status,
                'reason' => $request->reason,
                'decided_by' => $userId,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
        if ($n === 0) {
            return response()->json(['message' => 'Admission record not found'], 404);
        }
        $regStatus = $request->status === 'admitted' ? 'admitted' : 'not_admitted';
        DB::table('exam_registrations')
            ->where('exam_id', $id)
            ->where('student_user_id', $studentId)
            ->update(['status' => $regStatus, 'reason' => $request->reason, 'updated_at' => now()]);
        return response()->json(['message' => 'OK']);
    }

    public function admissionReport(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $e = $this->exam($id, $tenantId);
        if (!$e) {
            return response()->json(['message' => 'Exam not found'], 404);
        }
        $items = DB::table('exam_admissions')->where('exam_id', $id)->get();
        return response()->json(['data' => $items]);
    }

    public function setResult(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'results' => 'required|array|min:1',
            'results.*.student_user_id' => 'required|exists:users,id',
            'results.*.score' => 'nullable|numeric',
            'results.*.grade_value' => 'nullable|integer|min:0',
            'results.*.comment' => 'nullable|string|max:65535',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $userId = (int) auth()->id();
        $e = $this->exam($id, $tenantId);
        if (!$e) {
            return response()->json(['message' => 'Exam not found'], 404);
        }
        foreach ($request->results as $r) {
            $sid = $r['student_user_id'];
            $upd = [
                'score' => $r['score'] ?? null,
                'grade_value' => $r['grade_value'] ?? null,
                'comment' => $r['comment'] ?? null,
                'created_by' => $userId,
                'updated_at' => now(),
            ];
            $exists = DB::table('exam_results')->where('exam_id', $id)->where('student_user_id', $sid)->exists();
            if ($exists) {
                DB::table('exam_results')
                    ->where('exam_id', $id)
                    ->where('student_user_id', $sid)
                    ->update($upd);
            } else {
                $upd['exam_id'] = $id;
                $upd['student_user_id'] = $sid;
                $upd['created_at'] = now();
                DB::table('exam_results')->insert($upd);
            }
            $regStatus = isset($r['grade_value']) || isset($r['score']) ? 'passed' : 'registered';
            DB::table('exam_registrations')
                ->where('exam_id', $id)
                ->where('student_user_id', $sid)
                ->update(['status' => $regStatus, 'updated_at' => now()]);
        }
        return response()->json(['message' => 'OK']);
    }

    public function getResults(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $e = $this->exam($id, $tenantId);
        if (!$e) {
            return response()->json(['message' => 'Exam not found'], 404);
        }
        $items = DB::table('exam_results')->where('exam_id', $id)->get();
        return response()->json(['data' => $items]);
    }

    public function generateSheet(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $e = $this->exam($id, $tenantId);
        if (!$e) {
            return response()->json(['message' => 'Exam not found'], 404);
        }
        $results = DB::table('exam_results')->where('exam_id', $id)->get();
        $grades = $results->mapWithKeys(fn ($r) => [(string) $r->student_user_id => $r->grade_value ?? $r->score])->all();
        $stmtId = DB::table('exam_statements')->insertGetId([
            'exam_id' => $id,
            'document_id' => null,
            'grades' => json_encode($grades),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = DB::table('exam_statements')->where('id', $stmtId)->first();
        return response()->json(['data' => $row]);
    }

    public function getRules(Request $request, $termId): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $row = DB::table('exam_rules')->where('term_id', $termId)->first();
        return response()->json(['data' => $row]);
    }

    public function setRules(Request $request, $termId): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'config_json' => 'required|array',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $exists = DB::table('exam_rules')->where('term_id', $termId)->exists();
        if ($exists) {
            DB::table('exam_rules')->where('term_id', $termId)->update([
                'config_json' => json_encode($request->config_json),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('exam_rules')->insert([
                'term_id' => $termId,
                'config_json' => json_encode($request->config_json),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $row = DB::table('exam_rules')->where('term_id', $termId)->first();
        return response()->json(['data' => $row]);
    }

    private function exam($id, int $tenantId): ?object
    {
        $q = DB::table('exams')->where('id', $id);
        if (Schema::hasColumn('exams', 'tenant_id')) {
            $q->where('tenant_id', $tenantId);
        }
        return $q->first();
    }
}
