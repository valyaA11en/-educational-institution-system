<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value_json',
        'tenant_id',
        'readonly_mode',
    ];

    protected $casts = [
        'value_json' => 'array',
        'readonly_mode' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get setting value by key
     */
    public static function getValue(string $key, $default = null, $tenantId = null)
    {
        $query = static::where('key', $key);
        
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        
        $setting = $query->first();
        
        return $setting ? $setting->value_json : $default;
    }

    /**
     * Set setting value by key
     */
    public static function setValue(string $key, $value, $tenantId = null): bool
    {
        $query = static::where('key', $key);
        
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        
        $setting = $query->first();
        
        if ($setting) {
            // Check readonly mode
            if ($setting->readonly_mode) {
                return false;
            }
            $setting->value_json = $value;
            return $setting->save();
        } else {
            return static::create([
                'key' => $key,
                'value_json' => $value,
                'tenant_id' => $tenantId,
            ]) !== null;
        }
    }
}
