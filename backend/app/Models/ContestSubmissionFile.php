<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContestSubmissionFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'file_id',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ContestSubmission::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}

