<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PortfolioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'student_id' => 'nullable|exists:users,id',
            'type' => 'nullable|string|in:achievement,project,certificate,contest,other',
            'is_public' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $q = DB::table('portfolio_items')->where('tenant_id', $tenantId);

        if ($request->filled('student_id')) {
            $q->where('student_user_id', $request->student_id);
        } else {
            $q->where('student_user_id', auth()->id());
        }
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        if ($request->has('is_public')) {
            $q->where('is_public', (bool) $request->is_public);
        }

        $items = $q->orderBy('date', 'desc')->orderBy('id')->get();
        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'type' => 'required|in:achievement,project,certificate,contest,other',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'date' => 'required|date',
            'file' => 'nullable|file|max:10240',
            'is_public' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $userId = (int) auth()->id();

        $filePath = null;
        if ($request->hasFile('file')) {
            $f = $request->file('file');
            $path = $f->store('portfolio/' . $userId, 'local');
            $filePath = $path;
        }

        $id = DB::table('portfolio_items')->insertGetId([
            'tenant_id' => $tenantId,
            'student_user_id' => $userId,
            'type' => $request->type,
            'title' => $request->title,
            'description' => $request->description,
            'date' => $request->date,
            'file_path' => $filePath,
            'metadata_json' => null,
            'is_public' => (bool) $request->boolean('is_public'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('portfolio_items')->where('id', $id)->first();
        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'type' => 'sometimes|in:achievement,project,certificate,contest,other',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:65535',
            'date' => 'sometimes|date',
            'file' => 'nullable|file|max:10240',
            'is_public' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $tenantId = (int) auth()->user()->tenant_id;
        $userId = (int) auth()->id();

        $item = DB::table('portfolio_items')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->where('student_user_id', $userId)
            ->first();
        if (!$item) {
            return response()->json(['message' => 'Portfolio item not found'], 404);
        }

        $upd = array_filter([
            'type' => $request->type,
            'title' => $request->title,
            'description' => $request->description,
            'date' => $request->date,
            'is_public' => $request->has('is_public') ? (bool) $request->boolean('is_public') : null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($request->hasFile('file')) {
            if ($item->file_path && Storage::disk('local')->exists($item->file_path)) {
                Storage::disk('local')->delete($item->file_path);
            }
            $f = $request->file('file');
            $path = $f->store('portfolio/' . $userId, 'local');
            $upd['file_path'] = $path;
        }

        $upd['updated_at'] = now();
        DB::table('portfolio_items')->where('id', $id)->update($upd);

        $row = DB::table('portfolio_items')->where('id', $id)->first();
        return response()->json(['data' => $row]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $userId = (int) auth()->id();

        $item = DB::table('portfolio_items')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->where('student_user_id', $userId)
            ->first();
        if (!$item) {
            return response()->json(['message' => 'Portfolio item not found'], 404);
        }

        if ($item->file_path && Storage::disk('local')->exists($item->file_path)) {
            Storage::disk('local')->delete($item->file_path);
        }
        DB::table('portfolio_items')->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
