<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'timezone',
    ];

    public function members()
    {
        return $this->hasMany(TenantMember::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_members')
            ->withPivot('role_in_tenant')
            ->withTimestamps();
    }
}

