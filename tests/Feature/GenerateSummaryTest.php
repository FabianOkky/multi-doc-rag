<?php

use App\Agents\SummaryAgent;
use App\Jobs\GenerateSummary;
use App\Models\Document;
use App\Models\DocumentChunk;

test('it summarizes a document with a faked agent and marks it ready', function () {
    SummaryAgent::fake(['Ini ringkasan dokumen dalam beberapa kalimat yang faktual.']);

    $document = Document::factory()->create([
        'status' => Document::STATUS_PROCESSING,
        'summary' => null,
    ]);

    DocumentChunk::factory()->forDocument($document)->count(3)->create([
        'content' => 'Konten contoh yang akan diringkas oleh agen.',
    ]);

    GenerateSummary::dispatchSync($document);

    expect($document->refresh()->summary)
        ->toBe('Ini ringkasan dokumen dalam beberapa kalimat yang faktual.')
        ->and($document->status)->toBe(Document::STATUS_READY);

    // The agent was prompted with the document's own text — never the real Gemini API.
    SummaryAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Konten contoh'));
});

test('it marks a chunkless document ready without ever prompting the agent', function () {
    SummaryAgent::fake();

    $document = Document::factory()->create([
        'status' => Document::STATUS_PROCESSING,
        'summary' => null,
    ]);

    GenerateSummary::dispatchSync($document);

    expect($document->refresh()->status)->toBe(Document::STATUS_READY)
        ->and($document->summary)->toBeNull();

    // Nothing to summarize means no wasted call.
    SummaryAgent::assertNeverPrompted();
});

test('a failed summary call still leaves the document ready with no summary', function () {
    // The single agent call throws; the document must still become ready (its chunks
    // are embedded and queryable) with a null summary, and the job must not retry.
    SummaryAgent::fake(fn () => throw new RuntimeException('Gemini is unavailable.'));

    $document = Document::factory()->create([
        'status' => Document::STATUS_PROCESSING,
        'summary' => null,
    ]);

    DocumentChunk::factory()->forDocument($document)->create([
        'content' => 'Teks apa pun untuk diringkas.',
    ]);

    GenerateSummary::dispatchSync($document);

    expect($document->refresh()->status)->toBe(Document::STATUS_READY)
        ->and($document->summary)->toBeNull();
});
