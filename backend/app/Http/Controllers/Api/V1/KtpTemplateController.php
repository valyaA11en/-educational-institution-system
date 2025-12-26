<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KtpTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KtpTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KtpTemplate::with(['subject', 'creator']);

        if ($subjectId = $request->query('subjectId')) {
            $query->where('subject_id', $subjectId);
        }

        $templates = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => ['sometimes', 'nullable', 'integer', 'exists:subjects,id'],
            'name' => ['required', 'string', 'max:255'],
            'data_json' => ['sometimes', 'nullable', 'array'],
        ]);

        $validated['created_by'] = auth()->id();

        $template = KtpTemplate::create($validated);

        return response()->json($template->load(['subject', 'creator']), 201);
    }
}


