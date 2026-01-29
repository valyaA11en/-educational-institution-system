<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = (int) auth()->id();
        $q = DB::table('notifications')->where('user_id', $userId)->orderByDesc('created_at');

        if ($request->query('status')) {
            $q->where('status', $request->query('status'));
        }
        $items = $q->limit(100)->get()->map(fn ($n) => [
            'id' => $n->id,
            'type' => $n->type,
            'payload' => json_decode($n->payload_json ?? '{}', true) ?? [],
            'status' => $n->status,
            'createdAt' => $n->created_at,
            'readAt' => $n->read_at,
        ]);

        return response()->json(['data' => $items]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $userId = (int) auth()->id();
        $n = DB::table('notifications')->where('id', $id)->where('user_id', $userId)->first();
        if (!$n) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        DB::table('notifications')->where('id', $id)->update([
            'status' => 'read',
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'OK']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $userId = (int) auth()->id();
        DB::table('notifications')->where('user_id', $userId)->where('status', '!=', 'read')->update([
            'status' => 'read',
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'OK']);
    }

    public function delete(Request $request, $id): JsonResponse
    {
        $userId = (int) auth()->id();
        $n = DB::table('notifications')->where('id', $id)->where('user_id', $userId)->first();
        if (!$n) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        DB::table('notifications')->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function settings(Request $request): JsonResponse
    {
        $userId = (int) auth()->id();
        $s = DB::table('notification_settings')->where('user_id', $userId)->first();

        return response()->json([
            'data' => $s ? [
                'channel_prefs' => json_decode($s->channel_prefs ?? '{}', true) ?? [],
                'quiet_hours' => json_decode($s->quiet_hours ?? 'null', true),
                'digest_mode' => $s->digest_mode ?? 'none',
            ] : null,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $v = $request->validate([
            'channel_prefs' => 'nullable|array',
            'quiet_hours' => 'nullable|array',
            'digest_mode' => 'nullable|in:none,daily,weekly',
        ]);

        $userId = (int) auth()->id();
        $row = DB::table('notification_settings')->where('user_id', $userId)->first();

        $channelPrefs = isset($v['channel_prefs']) ? json_encode($v['channel_prefs']) : ($row?->channel_prefs ?? '{}');
        $quietHours = array_key_exists('quiet_hours', $v) ? json_encode($v['quiet_hours']) : ($row?->quiet_hours ?? null);
        $digestMode = $v['digest_mode'] ?? ($row?->digest_mode ?? 'none');

        if ($row) {
            DB::table('notification_settings')->where('user_id', $userId)->update([
                'channel_prefs' => $channelPrefs,
                'quiet_hours' => $quietHours,
                'digest_mode' => $digestMode,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('notification_settings')->insert([
                'user_id' => $userId,
                'channel_prefs' => $channelPrefs,
                'quiet_hours' => $quietHours,
                'digest_mode' => $digestMode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'OK']);
    }
}
