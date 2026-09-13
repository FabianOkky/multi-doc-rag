<?php

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Workspace;
use App\Services\Retriever;
use Laravel\Ai\Embeddings;

test('scoped retrieval returns the relevant chunk and never leaks another workspace', function () {
    // The query embeds to a deterministic unit vector; passing an array vector to
    // whereVectorSimilarTo keeps the search pure SQL (no Gemini call).
    Embeddings::fake([[unitVector(0)]]);

    $mine = Workspace::factory()->create();
    $other = Workspace::factory()->create();

    $myDocument = Document::factory()->for($mine)->ready()->create(['filename' => 'handbook.pdf']);
    $otherDocument = Document::factory()->for($other)->ready()->create(['filename' => 'confidential.pdf']);

    // Relevant chunk in my workspace (identical to the query → cosine similarity 1.0).
    DocumentChunk::factory()->forDocument($myDocument)->create([
        'embedding' => unitVector(0),
        'content' => 'The full onboarding procedure is described here.',
        'page_number' => 3,
    ]);

    // A perfect match, but it belongs to ANOTHER workspace and must never appear.
    DocumentChunk::factory()->forDocument($otherDocument)->create([
        'embedding' => unitVector(0),
        'content' => 'Confidential data belonging to another workspace.',
        'page_number' => 1,
    ]);

    $result = app(Retriever::class)->retrieve($mine, 'What is the procedure?');

    expect($result->chunks)->toHaveCount(1)
        ->and($result->chunks->first()->content)->toBe('The full onboarding procedure is described here.')
        ->and($result->chunks->first()->document_id)->toBe($myDocument->id);

    // Citations are derived only from the chunk that was actually retrieved.
    expect($result->citations)->toHaveCount(1)
        ->and($result->citations[0])->toBeCitation($myDocument->id, 'handbook.pdf', 3);

    // The other workspace's content is absent from both context and citations.
    expect($result->context)
        ->toContain('handbook.pdf, page 3')
        ->toContain('The full onboarding procedure is described here.')
        ->not->toContain('confidential.pdf')
        ->not->toContain('Confidential data belonging to another workspace.');
});

test('retrieval drops chunks below the minimum similarity threshold', function () {
    // Query vector is unit(0); the only chunk is orthogonal unit(1) → similarity 0,
    // which is below config('rag.min_similarity') (0.4), so nothing is returned.
    Embeddings::fake([[unitVector(0)]]);

    $workspace = Workspace::factory()->create();
    $document = Document::factory()->for($workspace)->ready()->create();

    DocumentChunk::factory()->forDocument($document)->create([
        'embedding' => unitVector(1),
        'content' => 'A completely unrelated topic.',
        'page_number' => 7,
    ]);

    $result = app(Retriever::class)->retrieve($workspace, 'Any question at all');

    expect($result->chunks)->toBeEmpty()
        ->and($result->citations)->toBe([])
        ->and($result->context)->toBe('')
        ->and($result->isEmpty())->toBeTrue();
});

test('retrieval excludes chunks that belong to a failed or processing document', function (string $status) {
    Embeddings::fake([[unitVector(0)]]);

    $workspace = Workspace::factory()->create();
    $document = Document::factory()->for($workspace)->create(['status' => $status]);

    DocumentChunk::factory()->forDocument($document)->create([
        'embedding' => unitVector(0),
        'content' => 'This stale passage must not be used as evidence.',
        'page_number' => 1,
    ]);

    $result = app(Retriever::class)->retrieve($workspace, 'What is the evidence?');

    expect($result->isEmpty())->toBeTrue()
        ->and($result->citations)->toBe([]);
})->with([
    'failed' => Document::STATUS_FAILED,
    'processing' => Document::STATUS_PROCESSING,
]);

test('citations are de-duplicated per document and page', function () {
    Embeddings::fake([[unitVector(0)]]);

    $workspace = Workspace::factory()->create();
    $document = Document::factory()->for($workspace)->ready()->create(['filename' => 'notes.pdf']);

    // Two equally-relevant chunks from the same document and page.
    DocumentChunk::factory()->forDocument($document)->count(2)->create([
        'embedding' => unitVector(0),
        'page_number' => 5,
    ]);

    $result = app(Retriever::class)->retrieve($workspace, 'What is in it?');

    expect($result->chunks->count())->toBeGreaterThan(1)
        ->and($result->citations)->toHaveCount(1)
        ->and($result->citations[0])->toBeCitation($document->id, 'notes.pdf', 5);
});
