<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TwoFactorAuth extends Model
{
    use HasFactory;

    protected $table = 'user_2fa';

    protected $fillable = [
        'user_id',
        'totp_secret',
        'enabled',
        'recovery_codes_json',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'recovery_codes_json' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

