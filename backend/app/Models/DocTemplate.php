<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocTemplate extends Model
{
    protected $table = 'doc_templates';

    protected $fillable = [
        'type',
        'name',
        'schema_json',
        'file_template_key',
        'tenant_id',
    ];

    protected $casts = [
        'schema_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the documents using this template.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'template_id');
    }
}
