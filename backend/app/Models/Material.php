<?php

namespace App\Models;

use App\Models\Scopes\VisibleToUserScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'title',
        'content',
        'visibility_scope',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new VisibleToUserScope());
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(MaterialTarget::class);
    }
}

