<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'group_id',
        'subject_id',
        'created_by',
        'name',
        'description',
        'is_announcement',
        'quiet_hours_start',
        'quiet_hours_end',
        'max_attachment_size',
        'allowed_attachment_types',
    ];

    protected function casts(): array
    {
        return [
            'is_announcement' => 'boolean',
            'quiet_hours_start' => 'datetime',
            'quiet_hours_end' => 'datetime',
            'allowed_attachment_types' => 'array',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_members', 'thread_id', 'user_id')
            ->withPivot('role_in_chat')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(ChatThreadSettings::class, 'thread_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ChatReport::class, 'thread_id');
    }
}

