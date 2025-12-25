<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocTemplate extends Model
{
    use HasFactory;

    protected $table = 'doc_templates';

    protected $fillable = [
        'type',
        'name',
        'schema_json',
        'file_template_key',
    ];

    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
        ];
    }
}

