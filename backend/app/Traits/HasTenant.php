<?php

namespace App\Traits;

use App\Scopes\TenantScope;

trait HasTenant
{
    public static function bootHasTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (!isset($model->tenant_id) && app()->bound('tenant_id')) {
                $model->tenant_id = app('tenant_id');
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}

