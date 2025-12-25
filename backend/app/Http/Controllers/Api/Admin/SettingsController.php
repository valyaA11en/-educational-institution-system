<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $keys = $request->query('keys', []);

        if (empty($keys)) {
            $settings = Setting::orderBy('key')->get();
        } else {
            $settings = Setting::whereIn('key', $keys)->orderBy('key')->get();
        }

        return response()->json([
            'data' => $settings->map(fn($s) => [
                'key' => $s->key,
                'value_json' => $s->value_json,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value_json' => ['required', 'array'],
        ]);

        $setting = $this->settingsService->set($validated['key'], $validated['value_json']);

        return response()->json([
            'key' => $setting->key,
            'value_json' => $setting->value_json,
        ], 201);
    }
}

