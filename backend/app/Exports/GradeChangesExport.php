<?php

namespace App\Exports;

use App\Models\GradeChange;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class GradeChangesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private ?int $studentId;
    private ?int $subjectId;
    private ?string $dateFrom;
    private ?string $dateTo;

    public function __construct(
        ?int $studentId = null,
        ?int $subjectId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ) {
        $this->studentId = $studentId;
        $this->subjectId = $subjectId;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = GradeChange::with(['grade.student', 'grade.lesson', 'grade.assignment', 'changer']);

        // Filter by student
        if ($this->studentId) {
            $query->whereHas('grade', function ($q) {
                $q->where('student_user_id', $this->studentId);
            });
        }

        // Filter by subject (through lesson or assignment)
        if ($this->subjectId) {
            $query->where(function ($q) {
                $q->whereHas('grade.lesson', function ($subQ) {
                    $subQ->where('subject_id', $this->subjectId);
                })->orWhereHas('grade.assignment', function ($subQ) {
                    $subQ->where('subject_id', $this->subjectId);
                });
            });
        }

        // Filter by date range
        if ($this->dateFrom) {
            $query->where('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('created_at', '<=', $this->dateTo . ' 23:59:59');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Дата изменения',
            'Студент',
            'Предмет',
            'Оценка до',
            'Оценка после',
            'Причина',
            'Изменено пользователем',
        ];
    }

    /**
     * @param GradeChange $change
     * @return array
     */
    public function map($change): array
    {
        $grade = $change->grade;
        $before = $change->before_json ?? [];
        $after = $change->after_json ?? [];

        $beforeValue = $before['value'] ?? '-';
        $afterValue = $after['value'] ?? '-';

        $studentName = $grade->student->fio ?? "ID: {$grade->student_user_id}";
        
        // Try to get subject from lesson, assignment, or directly from grade
        $subjectName = '-';
        if ($grade->lesson && $grade->lesson->subject) {
            $subjectName = $grade->lesson->subject->name ?? '-';
        } elseif ($grade->assignment && $grade->assignment->subject) {
            $subjectName = $grade->assignment->subject->name ?? '-';
        } elseif (isset($after['subject_name'])) {
            $subjectName = $after['subject_name'];
        } elseif (isset($before['subject_name'])) {
            $subjectName = $before['subject_name'];
        }

        $changerName = $change->changer->fio ?? "ID: {$change->changed_by}";

        return [
            $change->id,
            $change->created_at ? $change->created_at->format('d.m.Y H:i') : '-',
            $studentName,
            $subjectName,
            $beforeValue,
            $afterValue,
            $change->reason ?? '-',
            $changerName,
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        // Style header row
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Wrap text for reason column
        $sheet->getStyle('G2:G' . ($sheet->getHighestRow()))->getAlignment()->setWrapText(true);
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Изменения оценок';
    }
}

