<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WebhookEndpoint::query();

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('url', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('enabled')) {
            $query->where('enabled', $request->boolean('enabled'));
        }

        $webhooks = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return response()->json($webhooks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url'],
            'secret' => ['nullable', 'string', 'min:16'],
            'enabled' => ['sometimes', 'boolean'],
            'event_types' => ['required', 'array'],
            'event_types.*' => ['required', 'string'],
        ]);

        // Generate secret if not provided
        if (empty($validated['secret'])) {
            $validated['secret'] = Str::random(32);
        }

        $webhook = WebhookEndpoint::create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => $validated['secret'],
            'enabled' => $validated['enabled'] ?? true,
            'event_types' => $validated['event_types'],
        ]);

        return response()->json($webhook, 201);
    }

    public function show(int $id): JsonResponse
    {
        $webhook = WebhookEndpoint::with('deliveries')
            ->findOrFail($id);

        return response()->json($webhook);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $webhook = WebhookEndpoint::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url'],
            'secret' => ['sometimes', 'string', 'min:16'],
            'enabled' => ['sometimes', 'boolean'],
            'event_types' => ['sometimes', 'array'],
            'event_types.*' => ['required', 'string'],
        ]);

        $webhook->update($validated);

        return response()->json($webhook);
    }

    public function destroy(int $id): JsonResponse
    {
        $webhook = WebhookEndpoint::findOrFail($id);
        $webhook->delete();

        return response()->json(['message' => 'Webhook endpoint deleted']);
    }
}

