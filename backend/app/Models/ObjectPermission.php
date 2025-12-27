<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObjectPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'permission_id',
        'user_id',
        'role_id',
        'object_type',
        'object_id',
    ];

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}








