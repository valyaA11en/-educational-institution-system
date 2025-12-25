<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventStore extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'event_store';

    protected $fillable = [
        'channel_key',
        'event_type',
        'payload_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'created_at' => 'datetime',
        ];
    }
}


