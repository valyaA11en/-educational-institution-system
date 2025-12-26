<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DocTemplate::class);

        $query = DocTemplate::query();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $templates = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DocTemplate::class);

        $validated = $request->validate([
            'type' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'schema_json' => ['required', 'array'],
            'file_template_key' => ['sometimes', 'nullable', 'string'],
        ]);

        $template = DocTemplate::create($validated);

        return response()->json($template, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = DocTemplate::findOrFail($id);
        $this->authorize('update', $template);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'schema_json' => ['sometimes', 'array'],
            'file_template_key' => ['sometimes', 'nullable', 'string'],
        ]);

        $template->update($validated);

        return response()->json($template);
    }

    public function destroy(int $id): JsonResponse
    {
        $template = DocTemplate::findOrFail($id);
        $this->authorize('delete', $template);

        $template->delete();

        return response()->json(['message' => 'Deleted']);
    }
}


