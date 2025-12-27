<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasTenant;

class TimeSlot extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'order',
        'tenant_id',
    ];
}








