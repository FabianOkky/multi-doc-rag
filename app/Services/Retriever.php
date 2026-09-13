<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;

/**
 * Retrieves the document chunks most relevant to a question, always scoped to a
 * single workspace, and assembles the grounding context + citations for the chat
 * agent. Vector search runs in pgvector via whereVectorSimilarTo().
 *
 * Search is cross-lingual: the question is first rewritten into Indonesian and
 * English (QueryTranslator), every phrasing is searched, and the hits are merged
 * keeping each chunk's best distance. Documents are never translated, so every
 * citation still points at the user's own text.
 */
class Retriever
{
    public function __construct(private QueryTranslator $translator) {}

    /**
     * Find the chunks most similar to the question within the given workspace.
     */
    public function retrieve(Workspace $workspace, string $question): RetrievalResult
    {
        $query = $this->translator->translate($question);

        $chunks = $this->search($workspace, $this->embed($query->variants));

        return new RetrievalResult(
            $chunks,
            $this->buildContext($chunks),
            $this->buildCitations($chunks),
        );
    }

    /**
     * Embed every phrasing of the question in a single batch call, keeping the
     * vector search itself pure SQL (passing arrays, never strings, to the
     * query builder).
     *
     * @param  array<int, string>  $variants
     * @return array<int, array<int, float>>
     */
    private function embed(array $variants): array
    {
        return Embeddings::for($variants)->generate()->embeddings;
    }

    /**
     * Search the workspace once per query vector and merge the results, keeping
     * each chunk's smallest distance across phrasings so the most relevant hits
     * win regardless of which language matched them.
     *
     * @param  array<int, array<int, float>>  $vectors
     * @return Collection<int, DocumentChunk>
     */
    private function search(Workspace $workspace, array $vectors): Collection
    {
        $limit = (int) config('rag.retrieve_limit');

        /** @var array<int, DocumentChunk> $matches */
        $matches = [];

        foreach ($vectors as $vector) {
            foreach ($this->searchOne($workspace, $vector, $limit) as $chunk) {
                $best = $matches[$chunk->id] ?? null;

                if ($best === null || (float) $chunk->distance < (float) $best->distance) {
                    $matches[$chunk->id] = $chunk;
                }
            }
        }

        return (new Collection($matches))
            ->sortBy(fn (DocumentChunk $chunk): float => (float) $chunk->distance)
            ->take($limit)
            ->values();
    }

    /**
     * Run one similarity search, selecting the cosine distance so results from
     * different query vectors can be compared against each other.
     *
     * @param  array<int, float>  $vector
     * @return Collection<int, DocumentChunk>
     */
    private function searchOne(Workspace $workspace, array $vector, int $limit): Collection
    {
        return DocumentChunk::query()
            ->where('workspace_id', $workspace->id)
            ->whereHas('document', fn ($query) => $query->where('status', Document::STATUS_READY))
            ->select(['id', 'document_id', 'page_number', 'content'])
            ->selectVectorDistance('embedding', $vector, as: 'distance')
            ->with('document:id,filename')
            ->whereVectorSimilarTo('embedding', $vector, (float) config('rag.min_similarity'))
            ->limit($limit)
            ->get();
    }

    /**
     * Assemble the retrieved chunks into a single context block, each labelled
     * with its source so the agent can cite filename + page.
     *
     * @param  Collection<int, DocumentChunk>  $chunks
     */
    private function buildContext(Collection $chunks): string
    {
        return $chunks
            ->map(fn (DocumentChunk $chunk): string => '['.$this->sourceLabel($chunk)."]\n".$chunk->content)
            ->implode("\n\n");
    }

    /**
     * Build the unique list of citations from the retrieved chunks.
     *
     * @param  Collection<int, DocumentChunk>  $chunks
     * @return array<int, array{document_id: int, filename: string|null, page_number: int|null}>
     */
    private function buildCitations(Collection $chunks): array
    {
        return $chunks
            ->map(fn (DocumentChunk $chunk): array => [
                'document_id' => $chunk->document_id,
                'filename' => $chunk->document?->filename,
                'page_number' => $chunk->page_number,
            ])
            ->unique(fn (array $citation): string => $citation['document_id'].'@'.$citation['page_number'])
            ->values()
            ->all();
    }

    /**
     * Human-readable source label for a chunk, e.g. "laporan.pdf, page 3".
     *
     * The label is English because the agent's instructions are, and the agent
     * is told to reproduce it verbatim — keeping it stable in every answer
     * language so citations stay matchable against the chunks they came from.
     */
    private function sourceLabel(DocumentChunk $chunk): string
    {
        $filename = $chunk->document?->filename ?? 'document';

        return $chunk->page_number !== null
            ? "{$filename}, page {$chunk->page_number}"
            : $filename;
    }
}
