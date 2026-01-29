<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LdapConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'host',
        'port',
        'base_dn',
        'bind_dn',
        'bind_password',
        'user_filter',
        'attribute_mapping',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'attribute_mapping' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}


