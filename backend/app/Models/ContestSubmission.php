<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContestSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'contest_id',
        'participant_user_id',
        'title',
        'description',
    ];

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_user_id');
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'contest_submission_files', 'submission_id', 'file_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ContestScore::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(ContestResult::class);
    }
}

