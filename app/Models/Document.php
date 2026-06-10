<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'filename', 'file_type', 'file_url', 'summary', 'status'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_READY = 'ready';

    public const string STATUS_FAILED = 'failed';

    /**
     * The workspace this document belongs to.
     *
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The embedded chunks extracted from this document.
     *
     * @return HasMany<DocumentChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    /**
     * Mark the document as fully parsed, embedded, and ready to query.
     */
    public function markReady(): void
    {
        $this->update(['status' => self::STATUS_READY]);
    }

    /**
     * Mark the document as failed so the user knows processing did not finish.
     */
    public function markFailed(): void
    {
        $this->update(['status' => self::STATUS_FAILED]);
    }

    /**
     * Determine if the document finished processing and is ready to query.
     */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
