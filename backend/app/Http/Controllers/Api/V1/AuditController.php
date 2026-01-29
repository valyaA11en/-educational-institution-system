<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ImportExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function export(Request $request): StreamedResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $filters = $request->only(['entity', 'entity_id', 'user_id', 'date_from', 'date_to']);
        $filename = 'audit_export_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(
            function () use ($tenantId, $filters): void {
                $service = app(ImportExportService::class);
                echo "\xEF\xBB\xBF";
                foreach ($service->auditLogCsv($tenantId, $filters) as $line) {
                    echo $line;
                }
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ],
            'attachment'
        );
    }
}
