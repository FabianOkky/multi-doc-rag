<?php

namespace App\Jobs;

use App\Agents\SummaryAgent;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class GenerateSummary implements ShouldQueue
{
    use Queueable;

    /**
     * Run exactly once: the summary is a best-effort enhancement and we never
     * want to spend more than one Gemini call per document. A worker death is
     * handled by failed() rather than a retry.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job may run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public Document $document) {}

    /**
     * Summarize the document's text and mark it ready.
     *
     * The document is already queryable once its chunks are embedded, so the
     * summary is best-effort: whether it succeeds, fails, or there is nothing
     * to summarize, the document still transitions to "ready" in one update.
     */
    public function handle(): void
    {
        $this->document->update([
            'summary' => $this->generateSummary(),
            'status' => Document::STATUS_READY,
        ]);
    }

    /**
     * Generate the summary, or null if there is nothing to summarize or the
     * single agent call fails (we never retry to protect the quota).
     */
    private function generateSummary(): ?string
    {
        $text = $this->summarizableText();

        if ($text === '') {
            return null;
        }

        try {
            $summary = trim((new SummaryAgent)->prompt($text, provider: config('rag.text_failover'))->text);

            return $summary !== '' ? $summary : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Build the (token-limited) text sent to the summary agent from the start
     * of the document's chunks, in document order.
     */
    private function summarizableText(): string
    {
        $maxChars = (int) config('rag.summary_input_chars', 6000);

        $text = $this->document->chunks()
            ->orderBy('id')
            ->pluck('content')
            ->implode("\n\n");

        return Str::limit(trim($text), $maxChars, '');
    }

    /**
     * If the job dies outside handle() (timeout or worker kill), still mark the
     * document ready — its chunks are embedded and usable without a summary.
     */
    public function failed(?Throwable $exception): void
    {
        $this->document->markReady();
    }
}
