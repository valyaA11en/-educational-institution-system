<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLinkParentChild extends Model
{
    protected $table = 'user_links_parent_child';

    protected $fillable = [
        'parent_user_id',
        'student_user_id',
        'status',
        'link_code_hash',
    ];

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function studentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}








