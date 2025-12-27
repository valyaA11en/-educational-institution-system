<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reports\PdfReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function __construct(
        private PdfReportService $pdfService
    ) {}

    public function journal(Request $request): Response
    {
        // TODO: Get data from request
        $data = [
            'group' => null,
            'subject' => null,
            'period' => '',
            'students' => [],
            'dates' => [],
            'grades' => [],
            'totals' => [],
        ];

        $pdf = $this->pdfService->generateJournal($data);
        
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="journal.pdf"',
        ]);
    }

    public function schedule(Request $request): Response
    {
        $type = $request->query('type', 'group');
        
        // TODO: Get data from request
        $data = [
            'group' => null,
            'period' => '',
            'schedule' => [],
        ];

        $pdf = $this->pdfService->generateSchedule($data, $type);
        
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="schedule.pdf"',
        ]);
    }

    public function gradeSheet(Request $request): Response
    {
        // TODO: Get data from request
        $data = [];

        $pdf = $this->pdfService->generateGradeSheet($data);
        
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="grade_sheet.pdf"',
        ]);
    }

    public function order(Request $request): Response
    {
        // TODO: Get data from request
        $data = [];

        $pdf = $this->pdfService->generateOrder($data);
        
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="order.pdf"',
        ]);
    }
}

