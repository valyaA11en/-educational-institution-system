<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImportExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('audit_log')->orderByDesc('id')->limit(500);
        if (Schema::hasColumn('audit_log', 'tenant_id')) {
            $q->where('tenant_id', $tenantId);
        }
        $items = $q->get();
        return response()->json(['data' => $items]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('audit_log')->where('id', $id);
        if (Schema::hasColumn('audit_log', 'tenant_id')) {
            $q->where('tenant_id', $tenantId);
        }
        $row = $q->first();
        if (!$row) {
            return response()->json(['message' => 'Not found'], 404);
        }
        return response()->json(['data' => $row]);
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
