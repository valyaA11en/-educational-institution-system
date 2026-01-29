<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class PdfReportService
{
    public function generateJournal(array $data): string
    {
        $pdf = Pdf::loadView('reports.journal', $data);
        return $pdf->output();
    }

    public function generateGradeSheet(array $data): string
    {
        $pdf = Pdf::loadView('reports.grade_sheet', $data);
        return $pdf->output();
    }

    public function generateSchedule(array $data, string $type = 'group'): string
    {
        $view = match($type) {
            'group' => 'reports.schedule_group',
            'room' => 'reports.schedule_room',
            'teacher' => 'reports.schedule_teacher',
            default => 'reports.schedule_group',
        };
        
        $pdf = Pdf::loadView($view, $data);
        return $pdf->output();
    }

    public function generateOrder(array $data): string
    {
        $pdf = Pdf::loadView('reports.order', $data);
        return $pdf->output();
    }
}


