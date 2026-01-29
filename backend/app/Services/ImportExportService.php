<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

final class ImportExportService
{
    /**
     * @return \Generator<string>
     */
    public function usersCsv(int $tenantId, array $filters = []): \Generator
    {
        $headers = ['id', 'fio', 'email', 'phone', 'status', 'tenant_id', 'created_at'];
        yield $this->csvLine($headers);

        $q = DB::table('users')->where('tenant_id', $tenantId)->orderBy('id');
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        foreach ($q->cursor() as $row) {
            yield $this->csvLine([
                $row->id,
                $row->fio ?? '',
                $row->email ?? '',
                $row->phone ?? '',
                $row->status ?? 'active',
                $row->tenant_id ?? '',
                $row->created_at ?? '',
            ]);
        }
    }

    /**
     * @return \Generator<string>
     */
    public function groupsCsv(int $tenantId): \Generator
    {
        $headers = ['id', 'name', 'code', 'tenant_id', 'created_at'];
        yield $this->csvLine($headers);

        $q = DB::table('groups')->where('tenant_id', $tenantId)->orderBy('id');
        foreach ($q->cursor() as $row) {
            yield $this->csvLine([
                $row->id,
                $row->name ?? '',
                $row->code ?? '',
                $row->tenant_id ?? '',
                $row->created_at ?? '',
            ]);
        }
    }

    /**
     * @return \Generator<string>
     */
    public function scheduleCsv(int $tenantId, array $filters = []): \Generator
    {
        $headers = ['id', 'version_id', 'date', 'time_slot_id', 'group_id', 'subject_id', 'teacher_user_id', 'room_id', 'subgroup_id'];
        yield $this->csvLine($headers);

        $q = DB::table('schedule_items')
            ->join('schedule_versions', 'schedule_items.version_id', '=', 'schedule_versions.id')
            ->where('schedule_versions.tenant_id', $tenantId)
            ->select('schedule_items.*');
        if (!empty($filters['version_id'])) {
            $q->where('schedule_items.version_id', $filters['version_id']);
        }
        if (!empty($filters['date_from'])) {
            $q->where('schedule_items.date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->where('schedule_items.date', '<=', $filters['date_to']);
        }
        if (!empty($filters['group_id'])) {
            $q->where('schedule_items.group_id', $filters['group_id']);
        }
        $q->orderBy('schedule_items.date')->orderBy('schedule_items.time_slot_id');

        foreach ($q->cursor() as $row) {
            yield $this->csvLine([
                $row->id,
                $row->version_id,
                $row->date,
                $row->time_slot_id,
                $row->group_id,
                $row->subject_id,
                $row->teacher_user_id,
                $row->room_id,
                $row->subgroup_id ?? '',
            ]);
        }
    }

    /**
     * @return \Generator<string>
     */
    public function journalCsv(int $tenantId, array $filters = []): \Generator
    {
        $headers = ['lesson_id', 'date', 'group_id', 'subject_id', 'student_user_id', 'student_fio', 'grade_value'];
        yield $this->csvLine($headers);

        $q = DB::table('grades')
            ->join('lessons', 'grades.lesson_id', '=', 'lessons.id')
            ->join('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->leftJoin('users as u', 'grades.student_user_id', '=', 'u.id')
            ->where('grades.tenant_id', $tenantId)
            ->whereNotNull('grades.lesson_id')
            ->select(
                'lessons.id as lesson_id',
                'lessons.date',
                'schedule_items.group_id',
                'schedule_items.subject_id',
                'grades.student_user_id',
                'u.fio as student_fio',
                'grades.value as grade_value'
            );
        if (!empty($filters['group_id'])) {
            $q->where('schedule_items.group_id', $filters['group_id']);
        }
        if (!empty($filters['subject_id'])) {
            $q->where('schedule_items.subject_id', $filters['subject_id']);
        }
        if (!empty($filters['date_from'])) {
            $q->where('lessons.date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->where('lessons.date', '<=', $filters['date_to']);
        }
        $q->orderBy('lessons.date')->orderBy('grades.id');

        foreach ($q->cursor() as $row) {
            yield $this->csvLine([
                $row->lesson_id ?? '',
                $row->date ?? '',
                $row->group_id ?? '',
                $row->subject_id ?? '',
                $row->student_user_id ?? '',
                $row->student_fio ?? '',
                $row->grade_value ?? '',
            ]);
        }
    }

    /**
     * @return array{created: int, errors: array<int, string>}
     */
    public function importUsersFromCsv(int $tenantId, string $path): array
    {
        $created = 0;
        $errors = [];
        $defaultPassword = Hash::make('ChangeMe1!');
        $rowNum = 0;
        $fp = fopen($path, 'r');
        if (!$fp) {
            return ['created' => 0, 'errors' => [0 => 'Could not open file']];
        }
        $header = fgetcsv($fp, 0, ';') ?: [];
        $fioIdx = array_search('fio', $header) !== false ? array_search('fio', $header) : 1;
        $emailIdx = array_search('email', $header) !== false ? array_search('email', $header) : 2;
        $phoneIdx = array_search('phone', $header) !== false ? array_search('phone', $header) : 3;

        while (($r = fgetcsv($fp, 0, ';')) !== false) {
            $rowNum++;
            $fio = trim($r[$fioIdx] ?? '');
            $email = trim($r[$emailIdx] ?? '');
            $phone = trim($r[$phoneIdx] ?? '');
            if (!$fio && !$email) {
                $errors[$rowNum] = 'fio or email required';
                continue;
            }
            if ($email && DB::table('users')->where('email', $email)->exists()) {
                $errors[$rowNum] = 'email already exists';
                continue;
            }
            try {
                DB::table('users')->insert([
                    'fio' => $fio ?: 'User ' . $rowNum,
                    'email' => $email ?: null,
                    'phone' => $phone ?: null,
                    'password_hash' => $defaultPassword,
                    'status' => 'active',
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[$rowNum] = $e->getMessage();
            }
        }
        fclose($fp);
        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @return array{created: int, errors: array<int, string>}
     */
    public function importGroupsFromCsv(int $tenantId, string $path): array
    {
        $created = 0;
        $errors = [];
        $rowNum = 0;
        $fp = fopen($path, 'r');
        if (!$fp) {
            return ['created' => 0, 'errors' => [0 => 'Could not open file']];
        }
        $header = fgetcsv($fp, 0, ';') ?: [];
        $nameIdx = array_search('name', $header) !== false ? array_search('name', $header) : 1;
        $codeIdx = array_search('code', $header) !== false ? array_search('code', $header) : 2;

        while (($r = fgetcsv($fp, 0, ';')) !== false) {
            $rowNum++;
            $name = trim($r[$nameIdx] ?? '');
            $code = trim($r[$codeIdx] ?? '');
            if (!$name || !$code) {
                $errors[$rowNum] = 'name and code required';
                continue;
            }
            if (DB::table('groups')->where('code', $code)->where('tenant_id', $tenantId)->exists()) {
                $errors[$rowNum] = 'code already exists';
                continue;
            }
            try {
                DB::table('groups')->insert([
                    'name' => $name,
                    'code' => $code,
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[$rowNum] = $e->getMessage();
            }
        }
        fclose($fp);
        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @return array{created: int, errors: array<int, string>}
     */
    public function importScheduleFromCsv(int $tenantId, string $path): array
    {
        $created = 0;
        $errors = [];
        $rowNum = 0;
        $fp = fopen($path, 'r');
        if (!$fp) {
            return ['created' => 0, 'errors' => [0 => 'Could not open file']];
        }
        $header = fgetcsv($fp, 0, ';') ?: [];
        $versionIdx = array_search('version_id', $header) !== false ? array_search('version_id', $header) : 1;
        $dateIdx = array_search('date', $header) !== false ? array_search('date', $header) : 2;
        $tsIdx = array_search('time_slot_id', $header) !== false ? array_search('time_slot_id', $header) : 3;
        $groupIdx = array_search('group_id', $header) !== false ? array_search('group_id', $header) : 4;
        $subjIdx = array_search('subject_id', $header) !== false ? array_search('subject_id', $header) : 5;
        $teacherIdx = array_search('teacher_user_id', $header) !== false ? array_search('teacher_user_id', $header) : 6;
        $roomIdx = array_search('room_id', $header) !== false ? array_search('room_id', $header) : 7;
        $subgroupIdx = array_search('subgroup_id', $header) !== false ? array_search('subgroup_id', $header) : 8;

        while (($r = fgetcsv($fp, 0, ';')) !== false) {
            $rowNum++;
            $versionId = (int) ($r[$versionIdx] ?? 0);
            $date = trim($r[$dateIdx] ?? '');
            $timeSlotId = (int) ($r[$tsIdx] ?? 0);
            $groupId = (int) ($r[$groupIdx] ?? 0);
            $subjectId = (int) ($r[$subjIdx] ?? 0);
            $teacherId = (int) ($r[$teacherIdx] ?? 0);
            $roomId = (int) ($r[$roomIdx] ?? 0);
            $subgroupId = trim($r[$subgroupIdx] ?? '') ? (int) $r[$subgroupIdx] : null;
            if (!$versionId || !$date || !$timeSlotId || !$groupId || !$subjectId || !$teacherId || !$roomId) {
                $errors[$rowNum] = 'version_id, date, time_slot_id, group_id, subject_id, teacher_user_id, room_id required';
                continue;
            }
            $version = DB::table('schedule_versions')->where('id', $versionId)->where('tenant_id', $tenantId)->first();
            if (!$version) {
                $errors[$rowNum] = 'version not found or tenant mismatch';
                continue;
            }
            try {
                DB::table('schedule_items')->insert([
                    'version_id' => $versionId,
                    'date' => $date,
                    'time_slot_id' => $timeSlotId,
                    'group_id' => $groupId,
                    'subgroup_id' => $subgroupId,
                    'subject_id' => $subjectId,
                    'teacher_user_id' => $teacherId,
                    'room_id' => $roomId,
                    'tenant_id' => $tenantId,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[$rowNum] = $e->getMessage();
            }
        }
        fclose($fp);
        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @return array{created: int, errors: array<int, string>}
     */
    public function importGradesFromCsv(int $tenantId, string $path): array
    {
        $created = 0;
        $errors = [];
        $rowNum = 0;
        $fp = fopen($path, 'r');
        if (!$fp) {
            return ['created' => 0, 'errors' => [0 => 'Could not open file']];
        }
        $header = fgetcsv($fp, 0, ';') ?: [];
        $lessonIdx = array_search('lesson_id', $header) !== false ? array_search('lesson_id', $header) : 1;
        $studentIdx = array_search('student_user_id', $header) !== false ? array_search('student_user_id', $header) : 2;
        $valueIdx = array_search('value', $header) !== false ? array_search('value', $header) : 3;
        $weightIdx = array_search('weight', $header) !== false ? array_search('weight', $header) : 4;

        while (($r = fgetcsv($fp, 0, ';')) !== false) {
            $rowNum++;
            $lessonId = (int) ($r[$lessonIdx] ?? 0);
            $studentId = (int) ($r[$studentIdx] ?? 0);
            $value = (int) ($r[$valueIdx] ?? 0);
            $weight = (int) ($r[$weightIdx] ?? 1);
            if (!$lessonId || !$studentId || $value < 1 || $value > 5) {
                $errors[$rowNum] = 'lesson_id, student_user_id required; value 1–5';
                continue;
            }
            $lesson = DB::table('lessons')->where('id', $lessonId)->where('tenant_id', $tenantId)->first();
            if (!$lesson) {
                $errors[$rowNum] = 'lesson not found or tenant mismatch';
                continue;
            }
            try {
                DB::table('grades')->insert([
                    'lesson_id' => $lessonId,
                    'assignment_id' => null,
                    'student_user_id' => $studentId,
                    'value' => $value,
                    'weight' => $weight,
                    'tenant_id' => $tenantId,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[$rowNum] = $e->getMessage();
            }
        }
        fclose($fp);
        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * Single-student export CSV (one user row).
     *
     * @return \Generator<string>
     */
    public function studentExportCsv(object $user): \Generator
    {
        $headers = ['id', 'fio', 'email', 'phone', 'status', 'tenant_id', 'created_at'];
        yield $this->csvLine($headers);
        yield $this->csvLine([
            $user->id ?? '',
            $user->fio ?? '',
            $user->email ?? '',
            $user->phone ?? '',
            $user->status ?? 'active',
            $user->tenant_id ?? '',
            $user->created_at ?? '',
        ]);
    }

    /**
     * Student timeline export CSV (grades, assignments) for one student.
     *
     * @return \Generator<string>
     */
    public function timelineExportCsv(int $tenantId, int $studentId): \Generator
    {
        $headers = ['date', 'type', 'subject', 'value', 'comment'];
        yield $this->csvLine($headers);

        $grades = DB::table('grades')
            ->leftJoin('lessons', 'grades.lesson_id', '=', 'lessons.id')
            ->leftJoin('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->leftJoin('subjects', 'schedule_items.subject_id', '=', 'subjects.id')
            ->leftJoin('assignments', 'grades.assignment_id', '=', 'assignments.id')
            ->leftJoin('subjects as asub', 'assignments.subject_id', '=', 'asub.id')
            ->where('grades.student_user_id', $studentId)
            ->where('grades.tenant_id', $tenantId)
            ->select(
                'grades.value',
                'grades.created_at',
                DB::raw('COALESCE(subjects.name, asub.name) as subject_name')
            )
            ->orderBy('grades.created_at')
            ->get();

        foreach ($grades as $g) {
            $sub = $g->subject_name ?? '-';
            yield $this->csvLine([
                $g->created_at ?? '',
                'grade',
                $sub,
                $g->value ?? '',
                '',
            ]);
        }
    }

    /**
     * Audit log CSV export (tenant-scoped when tenant_id exists).
     *
     * @param  array{entity?: string, entity_id?: int, user_id?: int, date_from?: string, date_to?: string}  $filters
     * @return \Generator<string>
     */
    public function auditLogCsv(int $tenantId, array $filters = []): \Generator
    {
        $headers = ['id', 'user_id', 'action', 'entity', 'entity_id', 'ip', 'created_at'];
        yield $this->csvLine($headers);

        $q = DB::table('audit_log')->orderBy('id');
        if (Schema::hasColumn('audit_log', 'tenant_id')) {
            $q->where('tenant_id', $tenantId);
        }
        if (!empty($filters['entity'])) {
            $q->where('entity', $filters['entity']);
        }
        if (!empty($filters['entity_id'])) {
            $q->where('entity_id', (int) $filters['entity_id']);
        }
        if (!empty($filters['user_id'])) {
            $q->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['date_from'])) {
            $q->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
        foreach ($q->cursor() as $row) {
            yield $this->csvLine([
                $row->id ?? '',
                $row->user_id ?? '',
                $row->action ?? '',
                $row->entity ?? '',
                $row->entity_id ?? '',
                $row->ip ?? '',
                $row->created_at ?? '',
            ]);
        }
    }

    /**
     * @param  array<int|string>  $fields
     */
    private function csvLine(array $fields): string
    {
        $escaped = array_map(fn ($f) => '"' . str_replace('"', '""', (string) $f) . '"', $fields);

        return implode(';', $escaped) . "\n";
    }
}
