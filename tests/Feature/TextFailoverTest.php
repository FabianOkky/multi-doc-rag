<?php

use App\Agents\SummaryAgent;
use App\Jobs\GenerateSummary;
use App\Models\Document;
use App\Models\DocumentChunk;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\RateLimitedException;

test('text generation fails over to the backup provider when the primary is rate limited', function () {
    // A two-tier chain: Gemini first, then a backup. This is exactly the shape
    // config('rag.text_failover') takes once a fallback model env is set in prod.
    config(['rag.text_failover' => [
        Lab::Gemini->value => 'gemini-2.5-flash',
        Lab::Groq->value => 'llama-3.3-70b-versatile',
    ]]);

    // The primary provider behaves as if the Gemini free-tier quota is exhausted
    // (429 -> RateLimitedException, a FailoverableException); the backup answers.
    SummaryAgent::fake(function ($prompt, $attachments, $provider, $model) {
        if ($provider->name() === 'gemini') {
            throw RateLimitedException::forProvider('gemini', 429);
        }

        return 'A summary from the backup provider after the primary ran out of quota.';
    });

    $document = Document::factory()->create([
        'status' => Document::STATUS_PROCESSING,
        'summary' => null,
    ]);

    DocumentChunk::factory()->forDocument($document)->create([
        'content' => 'Document text waiting to be summarized.',
    ]);

    GenerateSummary::dispatchSync($document);

    // The summary the user sees came from the backup provider — the app kept
    // working through a primary-provider rate limit.
    expect($document->refresh()->summary)
        ->toBe('A summary from the backup provider after the primary ran out of quota.')
        ->and($document->status)->toBe(Document::STATUS_READY);
});

test('a single-provider chain (the default) generates normally with no failover', function () {
    // The shipped default is Gemini only; behavior is unchanged from before.
    config(['rag.text_failover' => [Lab::Gemini->value => 'gemini-2.5-flash']]);

    SummaryAgent::fake(['An ordinary summary from the primary provider.']);

    $document = Document::factory()->create([
        'status' => Document::STATUS_PROCESSING,
        'summary' => null,
    ]);

    DocumentChunk::factory()->forDocument($document)->create([
        'content' => 'Document text waiting to be summarized.',
    ]);

    GenerateSummary::dispatchSync($document);

    expect($document->refresh()->summary)->toBe('An ordinary summary from the primary provider.');
});
