<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\HasTenant;

class Group extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'name',
        'code',
        'tenant_id',
    ];

    public function subgroups(): HasMany
    {
        return $this->hasMany(Subgroup::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('role_in_group')
            ->withTimestamps();
    }
}


