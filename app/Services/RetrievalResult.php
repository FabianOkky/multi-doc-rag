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

    /**
     * Return only sources the generated answer explicitly names.
     *
     * Retrieved passages are candidates, not proof that the model used every
     * one. Requiring the filename and, when available, page reference prevents
     * unrelated retrieval hits from being presented as evidence.
     *
     * @return array<int, array{document_id: int, filename: string|null, page_number: int|null}>
     */
    public function citationsUsedBy(string $answer): array
    {
        return (new Collection($this->citations))
            ->filter(function (array $citation) use ($answer): bool {
                $filename = $citation['filename'];

                if ($filename === null || mb_stripos($answer, $filename) === false) {
                    return false;
                }

                $page = $citation['page_number'];

                if ($page === null) {
                    return true;
                }

                return preg_match('/(?:page|hal(?:aman)?\.?|p\.)\s*'.preg_quote((string) $page, '/').'\b/iu', $answer) === 1;
            })
            ->values()
            ->all();
    }
}
