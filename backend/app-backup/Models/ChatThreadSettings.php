<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatThreadSettings extends Model
{
    use HasFactory;

    protected $table = 'chat_thread_settings';

    protected $fillable = [
        'thread_id',
        'mode',
        'quiet_hours',
        'attachments_enabled',
    ];

    protected function casts(): array
    {
        return [
            'quiet_hours' => 'array',
            'attachments_enabled' => 'boolean',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }
}


