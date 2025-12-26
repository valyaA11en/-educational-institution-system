<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('settings.manage');

        $webhooks = Webhook::where('created_by', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($webhooks);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('settings.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url'],
            'method' => ['sometimes', 'in:POST,PUT,PATCH'],
            'events' => ['required', 'array'],
            'events.*' => ['string'],
            'headers' => ['nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $webhook = Webhook::create([
            ...$validated,
            'created_by' => auth()->id(),
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json($webhook, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);
        $this->authorize('update', $webhook);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url'],
            'method' => ['sometimes', 'in:POST,PUT,PATCH'],
            'events' => ['sometimes', 'array'],
            'events.*' => ['string'],
            'headers' => ['nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $webhook->update($validated);

        return response()->json($webhook);
    }

    public function destroy(int $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);
        $this->authorize('delete', $webhook);

        $webhook->delete();

        return response()->json(['message' => 'Webhook deleted']);
    }

    public static function trigger(string $event, array $payload): void
    {
        $webhooks = Webhook::where('active', true)
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($webhooks as $webhook) {
            try {
                $response = Http::withHeaders($webhook->headers ?? [])
                    ->timeout(10)
                    ->send($webhook->method, $webhook->url, [
                        'json' => [
                            'event' => $event,
                            'timestamp' => now()->toIso8601String(),
                            'payload' => $payload,
                        ],
                    ]);

                if (!$response->successful()) {
                    Log::warning('Webhook failed', [
                        'webhook_id' => $webhook->id,
                        'event' => $event,
                        'status' => $response->status(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Webhook error', [
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

