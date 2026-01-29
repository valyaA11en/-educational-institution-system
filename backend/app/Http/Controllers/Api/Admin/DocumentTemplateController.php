<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class DocumentTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = DocTemplate::query()->orderBy('id');
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        if (Schema::hasColumn('doc_templates', 'tenant_id')) {
            $tid = (int) auth()->user()->tenant_id;
            $q->where(function ($q) use ($tid) {
                $q->where('tenant_id', $tid)->orWhereNull('tenant_id');
            });
        }
        $items = $q->get();
        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'type' => 'required|string|max:64|in:order,decision,memo,protocol,statement,grade_sheet',
            'name' => 'required|string|max:255',
            'schema_json' => 'required|array',
            'file_template_key' => 'nullable|string|max:512',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $attrs = [
            'type' => $request->type,
            'name' => $request->name,
            'schema_json' => $request->schema_json,
            'file_template_key' => $request->file_template_key,
        ];
        if (Schema::hasColumn('doc_templates', 'tenant_id')) {
            $attrs['tenant_id'] = (int) auth()->user()->tenant_id;
        }
        $t = DocTemplate::create($attrs);
        return response()->json(['data' => $t], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'type' => 'sometimes|string|max:64|in:order,decision,memo,protocol,statement,grade_sheet',
            'name' => 'sometimes|string|max:255',
            'schema_json' => 'sometimes|array',
            'file_template_key' => 'nullable|string|max:512',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $q = DocTemplate::query()->where('id', $id);
        if (Schema::hasColumn('doc_templates', 'tenant_id')) {
            $q->where('tenant_id', (int) auth()->user()->tenant_id);
        }
        $t = $q->first();
        if (!$t) {
            return response()->json(['message' => 'Template not found'], 404);
        }
        $t->update($request->only(['type', 'name', 'schema_json', 'file_template_key']));
        return response()->json(['data' => $t->fresh()]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $q = DocTemplate::query()->where('id', $id);
        if (Schema::hasColumn('doc_templates', 'tenant_id')) {
            $q->where('tenant_id', (int) auth()->user()->tenant_id);
        }
        $t = $q->first();
        if (!$t) {
            return response()->json(['message' => 'Template not found'], 404);
        }
        $t->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
