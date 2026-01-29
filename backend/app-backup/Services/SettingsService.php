<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        return $setting->value_json ?? $default;
    }

    public function set(string $key, mixed $valueJson): Setting
    {
        return Setting::updateOrCreate(
            ['key' => $key],
            ['value_json' => $valueJson]
        );
    }
}

