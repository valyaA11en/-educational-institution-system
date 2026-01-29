<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'type',
        'attempts',
        'locked_until',
        'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'locked_until' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
        $this->last_attempt_at = now();

        // Lock after 10 failed attempts for 15 minutes
        if ($this->attempts >= 10) {
            $this->locked_until = now()->addMinutes(15);
        }

        $this->save();
    }

    public function resetAttempts(): void
    {
        $this->attempts = 0;
        $this->locked_until = null;
        $this->save();
    }
}


