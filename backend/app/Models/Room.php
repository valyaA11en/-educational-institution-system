<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasTenant;

class Room extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'name',
        'code',
        'capacity',
        'attributes',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
        ];
    }
}


