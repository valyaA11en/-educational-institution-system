<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DataImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataImportController extends Controller
{
    public function __construct(
        private DataImportService $importService
    ) {}

    /**
     * POST /api/import/users
     */
    public function importUsers(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');
        $path = $file->store('imports');

        try {
            $result = $this->importService->importUsers(storage_path("app/{$path}"));
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/import/groups
     */
    public function importGroups(Request $request): JsonResponse
    {
        // TODO: Реализовать
        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * POST /api/import/schedule
     */
    public function importSchedule(Request $request): JsonResponse
    {
        // TODO: Реализовать
        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * POST /api/import/grades
     */
    public function importGrades(Request $request): JsonResponse
    {
        // TODO: Реализовать
        return response()->json(['message' => 'Not implemented'], 501);
    }
}


