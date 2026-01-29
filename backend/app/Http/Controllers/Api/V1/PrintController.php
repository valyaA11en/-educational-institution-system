<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\ScheduleItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintController extends Controller
{
    /**
     * Print schedule (view: group|teacher|room, id, from-to).
     */
    public function schedule(Request $request): StreamedResponse|JsonResponse
    {
        $v = Validator::make($request->all(), [
            'view' => 'required|in:group,teacher,room',
            'id' => 'required|integer|min:1',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $from = $request->from ? Carbon::parse($request->from) : Carbon::today();
        $to = $request->to ? Carbon::parse($request->to) : Carbon::today()->addDays(6);

        $q = ScheduleItem::query()
            ->join('schedule_versions', 'schedule_items.version_id', '=', 'schedule_versions.id')
            ->where('schedule_versions.tenant_id', $tenantId)
            ->whereBetween('schedule_items.date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->with(['group', 'subject', 'teacher', 'room', 'subgroup', 'timeSlot'])
            ->select('schedule_items.*');

        if ($request->view === 'group') {
            $q->where('schedule_items.group_id', $request->id);
        } elseif ($request->view === 'teacher') {
            $q->where('schedule_items.teacher_user_id', $request->id);
        } else {
            $q->where('schedule_items.room_id', $request->id);
        }

        $rows = $q->orderBy('schedule_items.date')->orderBy('schedule_items.time_slot_id')->get();
        $items = $rows->groupBy(fn ($i) => $i->date->format('Y-m-d'));

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $tenantObj = (object) ['name' => $tenant->name ?? 'Учебное заведение'];

        return $this->pdfResponse('print.schedule', [
            'title' => 'Расписание',
            'tenant' => $tenantObj,
            'printed_at' => Carbon::now(),
            'from' => $from,
            'to' => $to,
            'items' => $items,
        ], 'schedule.pdf');
    }

    /**
     * Print journal (groupId, optional subjectId, termId).
     */
    public function journal(Request $request): StreamedResponse|JsonResponse
    {
        $v = Validator::make($request->all(), [
            'groupId' => 'required|exists:groups,id',
            'subjectId' => 'nullable|exists:subjects,id',
            'termId' => 'nullable|exists:terms,id',
            'format' => 'nullable|in:pdf',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $group = Group::where('id', $request->groupId)->where('tenant_id', $tenantId)->first();
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $subject = null;
        if ($request->subjectId) {
            $subject = DB::table('subjects')->where('id', $request->subjectId)->where('tenant_id', $tenantId)->first();
            if ($subject) {
                $subject = (object) ['name' => $subject->name];
            }
        }

        $term = null;
        if ($request->termId) {
            $term = DB::table('terms')->where('id', $request->termId)->first();
            if ($term) {
                $term = (object) ['name' => $term->name ?? ''];
            }
        }

        $versionQ = DB::table('schedule_versions')->where('tenant_id', $tenantId);
        if ($request->termId) {
            $versionQ->where('term_id', $request->termId);
        }
        $version = $versionQ->orderByDesc('id')->first();
        $versionId = $version->id ?? null;

        if (!$versionId) {
            return response()->json(['message' => 'No schedule version found for term'], 422);
        }

        $lessons = DB::table('lessons')
            ->join('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->where('schedule_items.version_id', $versionId)
            ->where('schedule_items.group_id', $request->groupId)
            ->where('lessons.tenant_id', $tenantId);
        if ($request->subjectId) {
            $lessons->where('schedule_items.subject_id', $request->subjectId);
        }
        $lessons = $lessons->orderBy('lessons.date')->orderBy('schedule_items.time_slot_id')
            ->select('lessons.*', 'schedule_items.subject_id')
            ->limit(50)
            ->get();

        $lessonIds = $lessons->pluck('id')->toArray();
        $students = DB::table('group_members')
            ->join('users', 'group_members.user_id', '=', 'users.id')
            ->where('group_members.group_id', $request->groupId)
            ->where('group_members.role_in_group', 'student')
            ->where('users.tenant_id', $tenantId)
            ->select('users.id', 'users.fio')
            ->orderBy('users.fio')
            ->get();

        $gradesRaw = [];
        if (!empty($lessonIds)) {
            $g = DB::table('grades')->whereIn('lesson_id', $lessonIds)->get();
            foreach ($g as $gr) {
                $gradesRaw[$gr->student_user_id][$gr->lesson_id][] = (object) ['value' => $gr->value, 'assignment' => null];
            }
        }
        $grades = collect($gradesRaw)->map(fn ($byLesson) => collect($byLesson)->map(fn ($arr) => collect($arr)));

        $lessonsWithSubject = $lessons->map(function ($l) use ($tenantId) {
            $s = DB::table('subjects')->where('id', $l->subject_id)->first();
            $si = (object) ['subject' => $s ? (object) ['name' => $s->name] : (object) ['name' => '-']];
            $l->scheduleItem = $si;
            return $l;
        });

        $tenantRow = DB::table('tenants')->where('id', $tenantId)->first();
        $tenantObj = (object) ['name' => $tenantRow->name ?? 'Учебное заведение'];

        return $this->pdfResponse('print.journal', [
            'title' => 'Журнал',
            'tenant' => $tenantObj,
            'printed_at' => Carbon::now(),
            'group' => $group,
            'subject' => $subject,
            'term' => $term,
            'lessons' => $lessonsWithSubject,
            'students' => $students,
            'grades' => $grades,
        ], 'journal.pdf');
    }

    /**
     * Print attendance (groupId, from-to).
     */
    public function attendance(Request $request): StreamedResponse|JsonResponse
    {
        $v = Validator::make($request->all(), [
            'groupId' => 'required|exists:groups,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'format' => 'nullable|in:pdf',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $group = Group::where('id', $request->groupId)->where('tenant_id', $tenantId)->first();
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $from = $request->from ? Carbon::parse($request->from) : Carbon::today();
        $to = $request->to ? Carbon::parse($request->to) : $from->copy()->addDays(13);

        $students = DB::table('group_members')
            ->join('users', 'group_members.user_id', '=', 'users.id')
            ->where('group_members.group_id', $request->groupId)
            ->where('group_members.role_in_group', 'student')
            ->where('users.tenant_id', $tenantId)
            ->select('users.id', 'users.fio')
            ->orderBy('users.fio')
            ->get();

        $dates = [];
        $c = $from->copy();
        while ($c->lte($to)) {
            $dates[] = $c->format('Y-m-d');
            $c->addDay();
        }

        $lessonIds = DB::table('lessons')
            ->join('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->where('schedule_items.group_id', $request->groupId)
            ->whereBetween('lessons.date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('lessons.tenant_id', $tenantId)
            ->pluck('lessons.id');

        $attendance = collect();
        if ($lessonIds->isNotEmpty()) {
            $rows = DB::table('attendance')->whereIn('lesson_id', $lessonIds)->get();
            $lessonsById = DB::table('lessons')->whereIn('id', $lessonIds)->get()->keyBy('id');
            $tmp = [];
            foreach ($rows as $r) {
                $date = $lessonsById->get($r->lesson_id)?->date ?? null;
                if ($date) {
                    $tmp[$r->student_user_id][$date][] = (object) ['status' => $r->status];
                }
            }
            foreach ($tmp as $sid => $byDate) {
                $attendance[$sid] = collect($byDate)->map(fn ($arr) => collect($arr));
            }
            $attendance = collect($attendance);
        }

        $tenantRow = DB::table('tenants')->where('id', $tenantId)->first();
        $tenantObj = (object) ['name' => $tenantRow->name ?? 'Учебное заведение'];

        return $this->pdfResponse('print.attendance', [
            'title' => 'Посещаемость',
            'tenant' => $tenantObj,
            'printed_at' => Carbon::now(),
            'group' => $group,
            'from' => $from,
            'to' => $to,
            'dates' => $dates,
            'students' => $students,
            'attendance' => $attendance,
        ], 'attendance.pdf');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pdfResponse(string $view, array $data, string $filename): StreamedResponse|JsonResponse
    {
        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, $data);
            return $pdf->stream($filename, ['Attachment' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'PDF generation failed. Install barryvdh/laravel-dompdf: composer require barryvdh/laravel-dompdf',
                'error' => $e->getMessage(),
            ], 501);
        }
    }
}
