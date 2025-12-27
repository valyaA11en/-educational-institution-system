<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasTenant;

class Subgroup extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'group_id',
        'name',
        'code',
        'tenant_id',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}








