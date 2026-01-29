<?php

namespace App\Exports;

use App\Models\Risk;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RisksExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private ?int $groupId;
    private ?string $level;
    private ?string $riskType;

    public function __construct(?int $groupId = null, ?string $level = null, ?string $riskType = null)
    {
        $this->groupId = $groupId;
        $this->level = $level;
        $this->riskType = $riskType;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Risk::with('user')
            ->whereNotNull('user_id');

        // Filter by group
        if ($this->groupId) {
            $studentIds = DB::table('group_members')
                ->where('group_id', $this->groupId)
                ->where('role_in_group', 'student')
                ->pluck('user_id');
            
            $query->whereIn('user_id', $studentIds);
        }

        // Filter by level
        if ($this->level) {
            $query->where('level', $this->level);
        }

        // Filter by risk type
        if ($this->riskType) {
            $query->where('risk_type', $this->riskType);
        }

        // Default to current term
        $currentTermId = DB::table('terms')->where('is_current', true)->value('id');
        if ($currentTermId) {
            $query->where('term_id', $currentTermId);
        }

        return $query->orderBy('score', 'desc')
            ->orderBy('calculated_at', 'desc')
            ->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Студент',
            'Тип риска',
            'Уровень',
            'Оценка',
            'Детали',
            'Рассчитано',
        ];
    }

    /**
     * @param Risk $risk
     * @return array
     */
    public function map($risk): array
    {
        $riskTypeLabels = [
            'avg_low' => 'Низкая средняя оценка',
            'absences_high' => 'Высокие пропуски',
            'debts_high' => 'Высокие долги',
            'no_activity' => 'Нет активности',
        ];

        $levelLabels = [
            'green' => 'Зеленый',
            'yellow' => 'Желтый',
            'red' => 'Красный',
        ];

        return [
            $risk->id,
            $risk->user->fio ?? "ID: {$risk->user_id}",
            $riskTypeLabels[$risk->risk_type] ?? $risk->risk_type,
            $levelLabels[$risk->level] ?? $risk->level,
            number_format($risk->score, 2),
            json_encode($risk->details_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $risk->calculated_at ? $risk->calculated_at->format('d.m.Y H:i') : '-',
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        // Style header row
        $sheet->getStyle('A1:G1')->applyFromArray([
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
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Wrap text for details column
        $sheet->getStyle('F2:F' . ($sheet->getHighestRow()))->getAlignment()->setWrapText(true);
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Риски студентов';
    }
}









