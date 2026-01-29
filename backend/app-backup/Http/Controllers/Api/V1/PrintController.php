<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\ScheduleItem;
use App\Models\Subject;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrintController extends Controller
{
    /**
     * Print schedule
     * GET /api/print/schedule?view=group|teacher|room&id=...&from=&to=&format=pdf
     */
    public function schedule(Request $request)
    {
        $this->authorize('viewAny', ScheduleItem::class);

        $validated = $request->validate([
            'view' => ['required', 'in:group,teacher,room'],
            'id' => ['required', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'format' => ['nullable', 'in:pdf', 'default:pdf'],
        ]);

        $view = $validated['view'];
        $id = $validated['id'];
        $from = $validated['from'] ? Carbon::parse($validated['from']) : Carbon::now()->startOfWeek();
        $to = $validated['to'] ? Carbon::parse($validated['to']) : Carbon::now()->endOfWeek();

        $query = ScheduleItem::with(['group', 'subgroup', 'subject', 'teacher', 'room'])
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->orderBy('date')
            ->orderBy('time_slot_id');

        $title = '';
        $entity = null;

        switch ($view) {
            case 'group':
                $query->where('group_id', $id);
                $entity = Group::findOrFail($id);
                $title = "Расписание группы: {$entity->name}";
                break;
            case 'teacher':
                $query->where('teacher_id', $id);
                $entity = User::findOrFail($id);
                $title = "Расписание преподавателя: {$entity->fio}";
                break;
            case 'room':
                $query->where('room_id', $id);
                $entity = \App\Models\Room::findOrFail($id);
                $title = "Расписание кабинета: {$entity->name}";
                break;
        }

        $items = $query->get()->groupBy('date');

        $tenant = app('tenant') ?? \App\Models\Tenant::first();

        $data = [
            'title' => $title,
            'tenant' => $tenant,
            'items' => $items,
            'from' => $from,
            'to' => $to,
            'printed_at' => now(),
        ];

        $pdf = Pdf::loadView('print.schedule', $data);
        return $pdf->download("schedule_{$view}_{$id}_{$from->format('Y-m-d')}.pdf");
    }

    /**
     * Print journal
     * GET /api/print/journal?groupId=&subjectId=&termId=&format=pdf
     */
    public function journal(Request $request)
    {
        $this->authorize('viewAny', \App\Models\Grade::class);

        $validated = $request->validate([
            'groupId' => ['required', 'integer'],
            'subjectId' => ['nullable', 'integer'],
            'termId' => ['nullable', 'integer'],
            'format' => ['nullable', 'in:pdf', 'default:pdf'],
        ]);

        $groupId = $validated['groupId'];
        $subjectId = $validated['subjectId'] ?? null;
        $termId = $validated['termId'] ?? null;

        $group = Group::with(['members.user'])->findOrFail($groupId);
        $subject = $subjectId ? Subject::findOrFail($subjectId) : null;

        // Get lessons for this group/subject via schedule items
        $scheduleItemsQuery = ScheduleItem::where('group_id', $groupId);
        
        if ($subjectId) {
            $scheduleItemsQuery->where('subject_id', $subjectId);
        }

        $scheduleItems = $scheduleItemsQuery->get();
        $lessons = \App\Models\Lesson::whereIn('schedule_item_id', $scheduleItems->pluck('id'))
            ->with(['scheduleItem.subject'])
            ->orderBy('date')
            ->get();

        // Get students
        $students = $group->members()
            ->with('user')
            ->get()
            ->map(fn($m) => $m->user)
            ->filter(fn($u) => $u && $u->roles()->where('name', 'student')->exists())
            ->sortBy('fio')
            ->values();

        // Get grades for each student
        $grades = \App\Models\Grade::whereIn('lesson_id', $lessons->pluck('id'))
            ->with(['lesson', 'assignment'])
            ->get()
            ->groupBy(['student_id', 'lesson_id']);

        $tenant = app('tenant') ?? \App\Models\Tenant::first();

        $data = [
            'title' => $subject ? "Журнал: {$subject->name}" : "Журнал группы: {$group->name}",
            'tenant' => $tenant,
            'group' => $group,
            'subject' => $subject,
            'students' => $students,
            'lessons' => $lessons,
            'grades' => $grades,
            'printed_at' => now(),
        ];

        $pdf = Pdf::loadView('print.journal', $data);
        return $pdf->download("journal_group_{$groupId}" . ($subjectId ? "_subject_{$subjectId}" : '') . ".pdf");
    }

    /**
     * Print attendance
     * GET /api/print/attendance?groupId=&from=&to=&format=pdf
     */
    public function attendance(Request $request)
    {
        $this->authorize('viewAny', \App\Models\Attendance::class);

        $validated = $request->validate([
            'groupId' => ['required', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'format' => ['nullable', 'in:pdf', 'default:pdf'],
        ]);

        $groupId = $validated['groupId'];
        $from = $validated['from'] ? Carbon::parse($validated['from']) : Carbon::now()->startOfMonth();
        $to = $validated['to'] ? Carbon::parse($validated['to']) : Carbon::now()->endOfMonth();

        $group = Group::with(['members.user'])->findOrFail($groupId);

        // Get students
        $students = $group->members()
            ->with('user')
            ->get()
            ->map(fn($m) => $m->user)
            ->filter(fn($u) => $u && $u->roles()->where('name', 'student')->exists())
            ->sortBy('fio')
            ->values();

        // Get attendance records
        $attendanceQuery = DB::table('attendance')
            ->where('group_id', $groupId)
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
        
        $attendanceRecords = $attendanceQuery->get();
        $attendance = $attendanceRecords->groupBy(['student_id', 'date']);

        $tenant = app('tenant') ?? \App\Models\Tenant::first();

        $data = [
            'title' => "Посещаемость группы: {$group->name}",
            'tenant' => $tenant,
            'group' => $group,
            'students' => $students,
            'attendance' => $attendance,
            'from' => $from,
            'to' => $to,
            'printed_at' => now(),
        ];

        $pdf = Pdf::loadView('print.attendance', $data);
        return $pdf->download("attendance_group_{$groupId}_{$from->format('Y-m-d')}.pdf");
    }
}

