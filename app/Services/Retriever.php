<?php

namespace App\Services;

use App\Models\DocumentChunk;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;

/**
 * Retrieves the document chunks most relevant to a question, always scoped to a
 * single workspace, and assembles the grounding context + citations for the chat
 * agent. Vector search runs in pgvector via whereVectorSimilarTo().
 */
class Retriever
{
    /**
     * Find the chunks most similar to the question within the given workspace.
     */
    public function retrieve(Workspace $workspace, string $question): RetrievalResult
    {
        $chunks = DocumentChunk::query()
            ->where('workspace_id', $workspace->id)
            ->select(['id', 'document_id', 'page_number', 'content'])
            ->with('document:id,filename')
            ->whereVectorSimilarTo('embedding', $this->embed($question), (float) config('rag.min_similarity'))
            ->limit((int) config('rag.retrieve_limit'))
            ->get();

        return new RetrievalResult(
            $chunks,
            $this->buildContext($chunks),
            $this->buildCitations($chunks),
        );
    }

    /**
     * Embed the question once into a query vector, keeping the vector search
     * itself pure SQL (passing an array, never a string, to the query builder).
     *
     * @return array<int, float>
     */
    private function embed(string $question): array
    {
        return Embeddings::for([$question])->generate()->embeddings[0];
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
     * Human-readable source label for a chunk, e.g. "laporan.pdf hal. 3".
     */
    private function sourceLabel(DocumentChunk $chunk): string
    {
        $filename = $chunk->document?->filename ?? 'dokumen';

        return $chunk->page_number !== null
            ? "{$filename} hal. {$chunk->page_number}"
            : $filename;
    }
}
