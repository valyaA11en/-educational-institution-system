<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('tenant_id')) {
            $tenantId = app('tenant_id');
            if ($tenantId) {
                $table = $model->getTable();
                $builder->where("{$table}.tenant_id", $tenantId);
            }
        }
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenant', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('withTenant', function (Builder $builder, ?int $tenantId) {
            return $builder->withoutGlobalScope($this)
                ->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
        });
    }
}


