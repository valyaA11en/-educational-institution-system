<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportExportController extends Controller
{
    public function exportUsers(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('users.read');

        $users = User::with(['roles', 'group'])->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Users');

        // Headers
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'ФИО');
        $sheet->setCellValue('C1', 'Email');
        $sheet->setCellValue('D1', 'Телефон');
        $sheet->setCellValue('E1', 'Группа');
        $sheet->setCellValue('F1', 'Роли');

        $row = 2;
        foreach ($users as $user) {
            $sheet->setCellValue('A' . $row, $user->id);
            $sheet->setCellValue('B' . $row, $user->fio);
            $sheet->setCellValue('C' . $row, $user->email);
            $sheet->setCellValue('D' . $row, $user->phone);
            $sheet->setCellValue('E' . $row, $user->group?->name);
            $sheet->setCellValue('F' . $row, $user->roles->pluck('name')->join(', '));
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = storage_path('app/exports/users_' . now()->format('Y-m-d_His') . '.xlsx');
        $writer->save($filename);

        return response()->download($filename)->deleteFileAfterSend();
    }

    public function exportGroups(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('directory.manage');

        $groups = Group::with(['subgroup', 'members'])->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Groups');

        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Название');
        $sheet->setCellValue('C1', 'Подгруппа');
        $sheet->setCellValue('D1', 'Количество студентов');

        $row = 2;
        foreach ($groups as $group) {
            $sheet->setCellValue('A' . $row, $group->id);
            $sheet->setCellValue('B' . $row, $group->name);
            $sheet->setCellValue('C' . $row, $group->subgroup?->name);
            $sheet->setCellValue('D' . $row, $group->members->count());
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = storage_path('app/exports/groups_' . now()->format('Y-m-d_His') . '.xlsx');
        $writer->save($filename);

        return response()->download($filename)->deleteFileAfterSend();
    }

    public function exportSchedule(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('schedule.read');

        $termId = $request->query('termId');
        $groupId = $request->query('groupId');

        $query = DB::table('schedule_items')
            ->join('subjects', 'schedule_items.subject_id', '=', 'subjects.id')
            ->join('groups', 'schedule_items.group_id', '=', 'groups.id')
            ->join('users', 'schedule_items.teacher_id', '=', 'users.id')
            ->leftJoin('rooms', 'schedule_items.room_id', '=', 'rooms.id')
            ->select(
                'schedule_items.id',
                'subjects.name as subject',
                'groups.name as group',
                'users.fio as teacher',
                'rooms.name as room',
                'schedule_items.day_of_week',
                'schedule_items.time_slot_id',
                'schedule_items.week_type'
            );

        if ($termId) {
            $query->where('schedule_items.term_id', $termId);
        }
        if ($groupId) {
            $query->where('schedule_items.group_id', $groupId);
        }

        $items = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Schedule');

        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Предмет');
        $sheet->setCellValue('C1', 'Группа');
        $sheet->setCellValue('D1', 'Преподаватель');
        $sheet->setCellValue('E1', 'Кабинет');
        $sheet->setCellValue('F1', 'День недели');
        $sheet->setCellValue('G1', 'Слот');
        $sheet->setCellValue('H1', 'Тип недели');

        $row = 2;
        foreach ($items as $item) {
            $sheet->setCellValue('A' . $row, $item->id);
            $sheet->setCellValue('B' . $row, $item->subject);
            $sheet->setCellValue('C' . $row, $item->group);
            $sheet->setCellValue('D' . $row, $item->teacher);
            $sheet->setCellValue('E' . $row, $item->room);
            $sheet->setCellValue('F' . $row, $item->day_of_week);
            $sheet->setCellValue('G' . $row, $item->time_slot_id);
            $sheet->setCellValue('H' . $row, $item->week_type);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = storage_path('app/exports/schedule_' . now()->format('Y-m-d_His') . '.xlsx');
        $writer->save($filename);

        return response()->download($filename)->deleteFileAfterSend();
    }

    public function exportJournal(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('journal.read');

        $subjectId = $request->query('subjectId');
        $groupId = $request->query('groupId');
        $termId = $request->query('termId');

        // TODO: Export journal basics (lessons, grades, attendance)
        // Simplified version

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Journal');

        $writer = new Xlsx($spreadsheet);
        $filename = storage_path('app/exports/journal_' . now()->format('Y-m-d_His') . '.xlsx');
        $writer->save($filename);

        return response()->download($filename)->deleteFileAfterSend();
    }

    public function importUsers(Request $request): JsonResponse
    {
        $this->authorize('users.create');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        $imported = 0;
        $errors = [];

        // Skip header row
        foreach ($sheet->getRowIterator(2) as $row) {
            try {
                $fio = $sheet->getCell('B' . $row->getRowIndex())->getValue();
                $email = $sheet->getCell('C' . $row->getRowIndex())->getValue();
                $phone = $sheet->getCell('D' . $row->getRowIndex())->getValue();

                if (!$fio || !$email) {
                    continue;
                }

                User::firstOrCreate(
                    ['email' => $email],
                    [
                        'fio' => $fio,
                        'phone' => $phone,
                        'password' => bcrypt('password'), // TODO: Generate secure password
                    ]
                );

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row {$row->getRowIndex()}: {$e->getMessage()}";
            }
        }

        return response()->json([
            'imported' => $imported,
            'errors' => $errors,
        ]);
    }
}

