<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ImportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DataImportController extends Controller
{
    public function __construct(
        private ImportExportService $service
    ) {}

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

    public function importGroups(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $path = $request->file('file')->getRealPath();
        $result = $this->service->importGroupsFromCsv($tenantId, $path);

        return response()->json([
            'message' => "Imported {$result['created']} groups",
            'created' => $result['created'],
            'errors' => $result['errors'],
        ]);
    }

    public function importSchedule(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $path = $request->file('file')->getRealPath();
        $result = $this->service->importScheduleFromCsv($tenantId, $path);

        return response()->json([
            'message' => "Imported {$result['created']} schedule items",
            'created' => $result['created'],
            'errors' => $result['errors'],
        ]);
    }

    public function importGrades(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $path = $request->file('file')->getRealPath();
        $result = $this->service->importGradesFromCsv($tenantId, $path);

        return response()->json([
            'message' => "Imported {$result['created']} grades",
            'created' => $result['created'],
            'errors' => $result['errors'],
        ]);
    }
}
