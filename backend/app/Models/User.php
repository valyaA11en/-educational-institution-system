<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
// use Laravel\Sanctum\HasApiTokens; // Temporarily disabled until Sanctum is fully installed

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable; // HasApiTokens temporarily removed

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
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the password field for authentication.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Get the name attribute (alias for fio).
     */
    public function getNameAttribute()
    {
        return $this->fio;
    }

    /**
     * Get the tenant memberships for the user.
     */
    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    /**
     * Get the tenants that the user belongs to.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_members')
                    ->withPivot('role_in_tenant')
                    ->withTimestamps();
    }

    /**
     * Get the roles for the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Get all permissions for the user (both direct and through roles).
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'object_permissions');
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permissionCode): bool
    {
        // Check direct permissions
        if ($this->permissions()->where('code', $permissionCode)->exists()) {
            return true;
        }

        // Check permissions through roles
        return $this->roles()->whereHas('permissions', function ($query) use ($permissionCode) {
            $query->where('code', $permissionCode);
        })->exists();
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
