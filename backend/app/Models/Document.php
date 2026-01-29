<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class Document extends Model
{
    protected $fillable = [
        'type',
        'number',
        'date',
        'status',
        'template_id',
        'data_json',
        'created_by',
        'signed_by',
        'signed_at',
        'verify_hash',
        'tenant_id',
    ];

    protected $casts = [
        'data_json' => 'array',
        'date' => 'date',
        'signed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the template for this document.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocTemplate::class, 'template_id');
    }

    /**
     * Get the creator of this document.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the signer of this document.
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    /**
     * Get the approval routes for this document.
     */
    public function routes(): HasMany
    {
        return $this->hasMany(DocumentRoute::class, 'document_id');
    }

    /**
     * Get the acknowledgments for this document.
     */
    public function acknowledgments(): HasMany
    {
        return $this->hasMany(DocumentAck::class, 'document_id');
    }

    /**
     * Generate verify hash for document
     */
    public static function generateVerifyHash(int $documentId, string $type, string $number, string $date): string
    {
        $data = "{$documentId}|{$type}|{$number}|{$date}";
        return hash('sha256', $data . config('app.key'));
    }

    /**
     * Scope to filter by tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
