<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'endpoint' => 'required|string|max:512',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $userId = (int) auth()->id();
        $endpoint = $request->endpoint;
        $keys = json_encode($request->keys);
        $exists = DB::table('push_subscriptions')->where('endpoint', $endpoint)->first();
        if ($exists) {
            if ((int) $exists->user_id !== $userId) {
                DB::table('push_subscriptions')->where('endpoint', $endpoint)->update([
                    'user_id' => $userId,
                    'keys_json' => $keys,
                    'updated_at' => now(),
                ]);
            }
        } else {
            DB::table('push_subscriptions')->insert([
                'user_id' => $userId,
                'endpoint' => $endpoint,
                'keys_json' => $keys,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json(['message' => 'Subscribed']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = $request->input('endpoint') ?? $request->query('endpoint');
        $v = Validator::make(['endpoint' => $endpoint], [
            'endpoint' => 'required|string|max:512',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $userId = (int) auth()->id();
        $n = DB::table('push_subscriptions')
            ->where('endpoint', $endpoint)
            ->where('user_id', $userId)
            ->delete();
        return response()->json(['message' => $n ? 'Unsubscribed' : 'OK']);
    }
}
