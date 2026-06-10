<?php

namespace App\Services;

use App\Models\DocumentChunk;
use Illuminate\Support\Collection;

/**
 * The outcome of a scoped retrieval: the relevant chunks, the assembled context
 * block sent to the agent, and the unique citations derived from those chunks.
 */
class RetrievalResult
{
    /**
     * @param  Collection<int, DocumentChunk>  $chunks
     * @param  array<int, array{document_id: int, filename: string|null, page_number: int|null}>  $citations
     */
    public function __construct(
        public Collection $chunks,
        public string $context,
        public array $citations,
    ) {}

    /**
     * Determine whether any relevant context was found.
     */
    public function isEmpty(): bool
    {
        return $this->chunks->isEmpty();
    }
}
