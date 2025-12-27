<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KtpTemplate extends Model
{
    use HasFactory;

    protected $table = 'ktp_templates';

    protected $fillable = [
        'subject_id',
        'name',
        'data_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data_json' => 'array',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}







