<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /**
     * GET /api/audit
     */
    public function index(Request $request): JsonResponse
    {
        // TODO: Проверка прав доступа (только admin/auditor)

        $query = AuditLog::query()->with('user')->orderBy('created_at', 'desc');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('entity_type')) {
            $query->where('entity', $request->input('entity_type'));
        }

        if ($request->has('entity_id')) {
            $query->where('entity_id', $request->input('entity_id'));
        }

        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate($request->input('per_page', 50));

        return response()->json($logs);
    }

    /**
     * GET /api/audit/export
     */
    public function export(Request $request)
    {
        // TODO: Экспорт в Excel/PDF
        return response()->json(['message' => 'Not implemented'], 501);
    }
}

