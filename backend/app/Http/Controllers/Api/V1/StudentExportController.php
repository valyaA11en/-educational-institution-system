<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ImportExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\DB;

class StudentExportController extends Controller
{
    public function export(Request $request, $id): StreamedResponse|JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $user = DB::table('users')->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$user) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $filename = 'student_' . $id . '_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(
            function () use ($user): void {
                $service = app(ImportExportService::class);
                echo "\xEF\xBB\xBF";
                foreach ($service->studentExportCsv($user) as $line) {
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
