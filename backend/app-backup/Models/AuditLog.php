<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasTenant;

class AuditLog extends Model
{
    use HasFactory, HasTenant;

    protected $table = 'audit_log';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'entity',
        'entity_id',
        'before_json',
        'after_json',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
