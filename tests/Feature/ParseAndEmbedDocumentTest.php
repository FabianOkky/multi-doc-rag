<?php

use App\Jobs\GenerateSummary;
use App\Jobs\ParseAndEmbedDocument;
use App\Models\Document;
use App\Models\Workspace;
use App\Services\DocumentParser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Embeddings;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

test('document jobs are unique and the queue retry window exceeds their timeouts', function () {
    $document = Document::factory()->create();
    $parseJob = new ParseAndEmbedDocument($document);
    $summaryJob = new GenerateSummary($document);

    expect($parseJob)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($summaryJob)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($parseJob->uniqueId())->toBe((string) $document->id)
        ->and($summaryJob->uniqueId())->toBe((string) $document->id)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan($parseJob->timeout)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan($summaryJob->timeout);
});

test('the job parses a txt document, embeds its chunks, and queues summary generation', function () {
    Storage::fake('local');
    Embeddings::fake();
    Bus::fake([GenerateSummary::class]);

    $workspace = Workspace::factory()->create();

    $path = 'documents/sample.txt';
    Storage::disk('local')->put($path, file_get_contents(base_path('tests/fixtures/sample.txt')));

    $document = Document::factory()->for($workspace)->create([
        'filename' => 'sample.txt',
        'file_type' => 'txt',
        'file_url' => $path,
        'status' => Document::STATUS_PROCESSING,
    ]);

    ParseAndEmbedDocument::dispatchSync($document);

    // The job hands off to GenerateSummary, which owns the "ready" transition, so
    // the document is still processing immediately after parse + embed.
    expect($document->refresh()->status)->toBe(Document::STATUS_PROCESSING);

    Bus::assertDispatched(GenerateSummary::class);

    $chunks = $document->chunks()->get();
    $dimensions = config('rag.embedding_dimensions');

    // The fixture is long enough to produce more than one chunk.
    expect($chunks->count())->toBeGreaterThan(1);

    $chunks->each(function ($chunk) use ($workspace, $dimensions) {
        expect($chunk->workspace_id)->toBe($workspace->id)
            ->and($chunk->page_number)->toBe(1)
            ->and($chunk->content)->not->toBe('')
            ->and($chunk->embedding)->toBeArray()
            ->and($chunk->embedding)->toHaveCount($dimensions);
    });

    Embeddings::assertGenerated(fn ($prompt) => count($prompt->inputs) === $chunks->count());
});

test('the job is idempotent and replaces existing chunks on re-run', function () {
    Storage::fake('local');
    Embeddings::fake();
    Bus::fake([GenerateSummary::class]);

    $workspace = Workspace::factory()->create();

    $path = 'documents/sample.txt';
    Storage::disk('local')->put($path, file_get_contents(base_path('tests/fixtures/sample.txt')));

    $document = Document::factory()->for($workspace)->create([
        'file_type' => 'txt',
        'file_url' => $path,
        'status' => Document::STATUS_PROCESSING,
    ]);

    ParseAndEmbedDocument::dispatchSync($document);
    $firstCount = $document->chunks()->count();

    ParseAndEmbedDocument::dispatchSync($document);
    $secondCount = $document->chunks()->count();

    expect($secondCount)->toBe($firstCount)
        ->and($document->refresh()->status)->toBe(Document::STATUS_PROCESSING);
});

test('the job marks the document failed when the file is missing and never calls the API', function () {
    Storage::fake('local');
    Embeddings::fake();
    Bus::fake([GenerateSummary::class]);

    $document = Document::factory()->create([
        'file_type' => 'txt',
        'file_url' => 'documents/missing.txt',
        'status' => Document::STATUS_PROCESSING,
    ]);

    ParseAndEmbedDocument::dispatchSync($document);

    expect($document->refresh()->status)->toBe(Document::STATUS_FAILED)
        ->and($document->chunks()->count())->toBe(0);

    Embeddings::assertNothingGenerated();
    Bus::assertNotDispatched(GenerateSummary::class);
});

test('the job falls back to Gemini extraction when local parsing is insufficient', function () {
    Storage::fake('local');
    Embeddings::fake();
    Bus::fake([GenerateSummary::class]);

    // A scanned PDF (or similar) yields little/no local text; the Gemini fallback
    // then supplies the text. Page numbers are not recoverable there, so null.
    $extracted = 'The full document text extracted by Gemini as a fallback when local extraction is insufficient.';
    AnonymousAgent::fake([$extracted]);

    $workspace = Workspace::factory()->create();

    // Below DocumentParser::MIN_USEFUL_CHARS (16), so local extraction is treated
    // as insufficient and the Gemini fallback is attempted.
    $path = 'documents/scanned.txt';
    Storage::disk('local')->put($path, 'Hi');

    $document = Document::factory()->for($workspace)->create([
        'filename' => 'scanned.txt',
        'file_type' => 'txt',
        'file_url' => $path,
        'status' => Document::STATUS_PROCESSING,
    ]);

    ParseAndEmbedDocument::dispatchSync($document);

    $chunks = $document->chunks()->get();

    expect($chunks)->not->toBeEmpty();

    $chunks->each(function ($chunk) {
        expect($chunk->page_number)->toBeNull()
            ->and($chunk->content)->toContain('extracted by Gemini');
    });

    Bus::assertDispatched(GenerateSummary::class);

    AnonymousAgent::assertPrompted(
        fn ($prompt) => str_contains($prompt->prompt, 'Extract all readable text')
    );
});

test('the docx parser preserves page breaks without duplicating text runs', function () {
    Storage::fake('local');

    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $textRun = $section->addTextRun();
    $textRun->addText('First page has enough readable text for local parsing.');
    $section->addPageBreak();
    $secondTextRun = $section->addTextRun();
    $secondTextRun->addText('Second page also has enough readable text for local parsing.');

    Storage::disk('local')->makeDirectory('documents');
    $path = Storage::disk('local')->path('documents/nested-page-break.docx');
    IOFactory::createWriter($phpWord, 'Word2007')->save($path);

    $pages = app(DocumentParser::class)->parse($path, 'docx');

    expect($pages)->toHaveCount(2)
        ->and($pages[0]['page_number'])->toBe(1)
        ->and($pages[0]['text'])->toBe('First page has enough readable text for local parsing.')
        ->and($pages[1]['page_number'])->toBe(2)
        ->and($pages[1]['text'])->toBe('Second page also has enough readable text for local parsing.');
});
