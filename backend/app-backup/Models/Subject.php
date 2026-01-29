<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasTenant;

class Subject extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'name',
        'code',
        'tenant_id',
    ];
}








