<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = (int) auth()->id();
        $items = DB::table('webhooks')->where('created_by', $userId)->orderBy('id')->get();
        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'method' => 'nullable|in:GET,POST,PUT,PATCH,DELETE',
            'events' => 'required|array|min:1',
            'events.*' => 'string|max:128',
            'headers' => 'nullable|array',
            'active' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $id = DB::table('webhooks')->insertGetId([
            'name' => $request->name,
            'url' => $request->url,
            'method' => $request->method ?? 'POST',
            'events' => json_encode($request->events),
            'headers' => $request->has('headers') ? json_encode($request->headers) : null,
            'active' => $request->boolean('active', true),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = DB::table('webhooks')->where('id', $id)->first();
        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:2048',
            'method' => 'nullable|in:GET,POST,PUT,PATCH,DELETE',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'string|max:128',
            'headers' => 'nullable|array',
            'active' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $userId = (int) auth()->id();
        $w = DB::table('webhooks')->where('id', $id)->where('created_by', $userId)->first();
        if (!$w) {
            return response()->json(['message' => 'Webhook not found'], 404);
        }
        $upd = array_filter([
            'name' => $request->name,
            'url' => $request->url,
            'method' => $request->method,
            'events' => $request->has('events') ? json_encode($request->events) : null,
            'headers' => $request->has('headers') ? json_encode($request->headers) : null,
            'active' => $request->has('active') ? (int) $request->boolean('active') : null,
        ], fn ($x) => $x !== null);
        $upd['updated_at'] = now();
        DB::table('webhooks')->where('id', $id)->update($upd);
        $row = DB::table('webhooks')->where('id', $id)->first();
        return response()->json(['data' => $row]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $userId = (int) auth()->id();
        $w = DB::table('webhooks')->where('id', $id)->where('created_by', $userId)->first();
        if (!$w) {
            return response()->json(['message' => 'Webhook not found'], 404);
        }
        DB::table('webhooks')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
