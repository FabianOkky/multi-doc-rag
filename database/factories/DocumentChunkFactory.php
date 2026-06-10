<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Ai\Embeddings;

/**
 * @extends Factory<DocumentChunk>
 */
class DocumentChunkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dimensions = (int) config('rag.embedding_dimensions', 768);

        return [
            'document_id' => Document::factory(),
            // Keep the chunk in the same workspace as its document so per-workspace
            // retrieval scoping stays consistent.
            'workspace_id' => fn (array $attributes) => Document::find($attributes['document_id'])?->workspace_id
                ?? Workspace::factory(),
            'content' => fake()->paragraphs(2, true),
            'page_number' => fake()->numberBetween(1, 12),
            // fakeEmbedding() is pure local math (no API call) and returns a
            // normalized vector of the configured dimensions.
            'embedding' => Embeddings::fakeEmbedding($dimensions),
        ];
    }

    /**
     * Tie the chunk to a specific document (and its workspace).
     */
    public function forDocument(Document $document): static
    {
        return $this->state(fn (array $attributes): array => [
            'document_id' => $document->id,
            'workspace_id' => $document->workspace_id,
        ]);
    }
}
