<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ImportExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\Validator;

class ImportExportController extends Controller
{
    public function __construct(
        private ImportExportService $service
    ) {}

    public function exportUsers(Request $request): StreamedResponse|JsonResponse
    {
        $v = Validator::make($request->all(), [
            'status' => 'nullable|in:active,blocked',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $filters = array_filter(['status' => $request->status]);
        $filename = 'users_export_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        return $this->streamCsv($filename, fn () => $this->service->usersCsv($tenantId, $filters));
    }

    public function exportGroups(Request $request): StreamedResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $filename = 'groups_export_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        return $this->streamCsv($filename, fn () => $this->service->groupsCsv($tenantId));
    }

    public function exportSchedule(Request $request): StreamedResponse|JsonResponse
    {
        $v = Validator::make($request->all(), [
            'version_id' => 'nullable|exists:schedule_versions,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'group_id' => 'nullable|exists:groups,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $filters = array_filter([
            'version_id' => $request->version_id,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'group_id' => $request->group_id,
        ]);
        $filename = 'schedule_export_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        return $this->streamCsv($filename, fn () => $this->service->scheduleCsv($tenantId, $filters));
    }

    public function exportJournal(Request $request): StreamedResponse|JsonResponse
    {
        $v = Validator::make($request->all(), [
            'group_id' => 'nullable|exists:groups,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $filters = array_filter([
            'group_id' => $request->group_id,
            'subject_id' => $request->subject_id,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ]);
        $filename = 'journal_export_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        return $this->streamCsv($filename, fn () => $this->service->journalCsv($tenantId, $filters));
    }

    public function importUsers(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $path = $request->file('file')->getRealPath();
        $result = $this->service->importUsersFromCsv($tenantId, $path);

        return response()->json([
            'message' => "Imported {$result['created']} users",
            'created' => $result['created'],
            'errors' => $result['errors'],
        ]);
    }

    private function streamCsv(string $filename, callable $generator): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($generator): void {
                echo "\xEF\xBB\xBF";
                foreach ($generator() as $line) {
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
