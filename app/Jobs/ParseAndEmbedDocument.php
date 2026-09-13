<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\Chunker;
use App\Services\DocumentParser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use RuntimeException;
use Throwable;

class ParseAndEmbedDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted (worker-level safety net;
     * deterministic parse/embed errors are caught and marked failed in-process).
     */
    public int $tries = 3;

    /**
     * The number of seconds the job may run before timing out.
     */
    public int $timeout = 300;

    /**
     * Keep duplicate parse requests locked beyond the longest allowed attempt.
     */
    public int $uniqueFor = 600;

    /**
     * Delay retries when an external parser or embedding provider is unavailable.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    /**
     * Hard cap on chunks per document to protect the embedding quota.
     */
    private const int MAX_CHUNKS = 500;

    /**
     * How many chunk texts to embed per API call.
     */
    private const int EMBEDDING_BATCH_SIZE = 100;

    /**
     * Create a new job instance.
     */
    public function __construct(public Document $document) {}

    /**
     * Parse the document, embed its chunks, and persist them.
     */
    public function handle(DocumentParser $parser, Chunker $chunker): void
    {
        try {
            $chunks = $this->extractChunks($parser, $chunker);

            if ($chunks === []) {
                throw new RuntimeException("No extractable text in document [{$this->document->id}].");
            }

            $embeddings = $this->embed(array_column($chunks, 'content'));

            $this->storeChunks($chunks, $embeddings);

            // The document stays "processing" until GenerateSummary finishes: that
            // job writes the summary (best-effort) and flips the status to "ready",
            // so the card's loading state covers embedding *and* summarizing.
            GenerateSummary::dispatch($this->document);
        } catch (Throwable $e) {
            report($e);

            if ($e::class !== RuntimeException::class) {
                throw $e;
            }

            $this->document->markFailed();
        }
    }

    /**
     * Parse every page and split it into page-tagged chunks.
     *
     * @return array<int, array{content: string, page_number: int|null}>
     */
    private function extractChunks(DocumentParser $parser, Chunker $chunker): array
    {
        $absolutePath = Storage::disk(config('filesystems.default'))
            ->path($this->document->file_url);

        $pages = $parser->parse($absolutePath, $this->document->file_type);

        $chunks = [];

        foreach ($pages as $page) {
            foreach ($chunker->chunk($page['text']) as $content) {
                $content = trim($content);

                if ($content === '') {
                    continue;
                }

                $chunks[] = [
                    'content' => $content,
                    'page_number' => $page['page_number'],
                ];

                if (count($chunks) >= self::MAX_CHUNKS) {
                    return $chunks;
                }
            }
        }

        return $chunks;
    }

    /**
     * Embed the given chunk texts in batches, preserving order.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<float>>
     */
    private function embed(array $texts): array
    {
        $embeddings = [];

        foreach (array_chunk($texts, self::EMBEDDING_BATCH_SIZE) as $batch) {
            foreach (Embeddings::for($batch)->generate()->embeddings as $vector) {
                $embeddings[] = $vector;
            }
        }

        return $embeddings;
    }

    /**
     * Replace any existing chunks and persist the freshly embedded ones.
     *
     * @param  array<int, array{content: string, page_number: int|null}>  $chunks
     * @param  array<int, array<float>>  $embeddings
     */
    private function storeChunks(array $chunks, array $embeddings): void
    {
        if (count($chunks) !== count($embeddings)) {
            throw new RuntimeException("Embedding count did not match chunk count for document [{$this->document->id}].");
        }

        DB::transaction(function () use ($chunks, $embeddings): void {
            $this->document->chunks()->delete();

            $models = [];

            foreach ($chunks as $index => $chunk) {
                $models[] = new DocumentChunk([
                    'workspace_id' => $this->document->workspace_id,
                    'content' => $chunk['content'],
                    'page_number' => $chunk['page_number'],
                    'embedding' => $embeddings[$index],
                ]);
            }

            $this->document->chunks()->saveMany($models);
        });
    }

    /**
     * The document id is the unit of work, regardless of who dispatches it.
     */
    public function uniqueId(): string
    {
        return (string) $this->document->getKey();
    }

    /**
     * Mark the document failed if the job dies outside the in-process catch
     * (e.g. timeout or worker kill).
     */
    public function failed(?Throwable $exception): void
    {
        $this->document->markFailed();
    }
}
