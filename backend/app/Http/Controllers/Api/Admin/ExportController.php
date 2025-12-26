<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ExportController extends Controller
{
    public function exportUsers()
    {
        return Excel::download(new class implements FromCollection, WithHeadings, WithMapping {
            public function collection()
            {
                return User::with(['roles', 'groups'])->get();
            }

            public function headings(): array
            {
                return ['fio', 'email', 'phone', 'role', 'group'];
            }

            public function map($user): array
            {
                $role = $user->roles->first()?->name ?? '';
                $group = $user->groups->first()?->name ?? '';

                return [
                    $user->fio,
                    $user->email ?? '',
                    $user->phone ?? '',
                    $role,
                    $group,
                ];
            }
        }, 'users.xlsx');
    }

    public function exportSchedule(Request $request)
    {
        $request->validate([
            'versionId' => ['required', 'integer', 'exists:schedule_versions,id'],
        ]);

        $version = ScheduleVersion::findOrFail($request->input('versionId'));

        return Excel::download(new class($version->id) implements FromCollection, WithHeadings, WithMapping {
            private int $versionId;

            public function __construct(int $versionId)
            {
                $this->versionId = $versionId;
            }

            public function collection()
            {
                return ScheduleItem::with(['group', 'subject', 'teacher', 'room', 'timeSlot'])
                    ->where('version_id', $this->versionId)
                    ->get();
            }

            public function headings(): array
            {
                return ['date', 'time_slot', 'group', 'subject', 'teacher_email', 'room'];
            }

            public function map($item): array
            {
                return [
                    $item->date->format('Y-m-d'),
                    $item->timeSlot->name ?? '',
                    $item->group->name ?? '',
                    $item->subject->name ?? '',
                    $item->teacher->email ?? '',
                    $item->room->name ?? '',
                ];
            }
        }, 'schedule.xlsx');
    }
}

