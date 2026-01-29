<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:512'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $user = auth()->user();

        // Check if subscription already exists
        $subscription = PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $validated['endpoint'])
            ->first();

        if ($subscription) {
            // Update existing subscription
            $subscription->update([
                'keys_json' => [
                    'p256dh' => $validated['keys']['p256dh'],
                    'auth' => $validated['keys']['auth'],
                ],
            ]);

            return response()->json([
                'message' => 'Push subscription updated',
                'subscription' => $subscription,
            ]);
        }

        // Create new subscription
        $subscription = PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $validated['endpoint'],
            'keys_json' => [
                'p256dh' => $validated['keys']['p256dh'],
                'auth' => $validated['keys']['auth'],
            ],
        ]);

        Log::info('Push subscription created', [
            'user_id' => $user->id,
            'endpoint' => $validated['endpoint'],
        ]);

        return response()->json([
            'message' => 'Push subscription created',
            'subscription' => $subscription,
        ], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:512'],
        ]);

        $user = auth()->user();

        $subscription = PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $validated['endpoint'])
            ->first();

        if ($subscription) {
            $subscription->delete();

            Log::info('Push subscription deleted', [
                'user_id' => $user->id,
                'endpoint' => $validated['endpoint'],
            ]);

            return response()->json([
                'message' => 'Push subscription removed',
            ]);
        }

        return response()->json([
            'message' => 'Subscription not found',
        ], 404);
    }
}
