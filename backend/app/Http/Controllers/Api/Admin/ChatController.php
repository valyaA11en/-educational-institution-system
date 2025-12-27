<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function reports(Request $request): JsonResponse
    {
        $this->authorize('moderate', \App\Models\ChatThread::class);

        $query = ChatReport::with(['thread', 'message', 'reporter']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($threadId = $request->query('threadId')) {
            $query->where('thread_id', $threadId);
        }

        $reports = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($reports);
    }

    public function updateReport(Request $request, int $id): JsonResponse
    {
        $this->authorize('moderate', \App\Models\ChatThread::class);

        $report = ChatReport::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:open,reviewed,closed'],
        ]);

        $report->update([
            'status' => $validated['status'],
        ]);

        return response()->json($report->load(['thread', 'message', 'reporter']));
    }
}









