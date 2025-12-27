<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\HasTenant;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fio',
        'email',
        'phone',
        'password_hash',
        'status',
        'tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // TODO: добавить нужные касты при необходимости
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function permissions()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role_in_group')
            ->withTimestamps();
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_members')
            ->withPivot('role_in_tenant')
            ->withTimestamps();
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function getTenantRole(?int $tenantId = null): ?string
    {
        $tenantId = $tenantId ?? app('tenant_id');
        if (!$tenantId) {
            return null;
        }

        $member = \App\Models\TenantMember::where('tenant_id', $tenantId)
            ->where('user_id', $this->id)
            ->first();

        return $member?->role_in_tenant;
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims(): array
    {
        $claims = [];
        
        // Include tenant_id if available
        if (app()->bound('tenant_id')) {
            $claims['tenant_id'] = app('tenant_id');
        } elseif ($this->tenant_id) {
            $claims['tenant_id'] = $this->tenant_id;
        }
        
        return $claims;
    }

    // TODO: добавить методы для object-level permissions
}
