<?php

namespace App\Services;

use App\Agents\SuggestionAgent;
use App\Models\Document;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Produces a few suggested starter questions for a workspace from its ready
 * documents' summaries. The result is cached per workspace so the SuggestionAgent
 * is prompted at most once per distinct set of summaries; the cache key embeds a
 * fingerprint of those summaries, so adding, removing, or re-summarizing a
 * document naturally invalidates the suggestions without any manual flushing.
 */
class Suggester
{
    /**
     * How long generated suggestions stay cached (seconds).
     */
    private const int CACHE_TTL = 86400;

    /**
     * Get the suggested questions for the workspace, generating (and caching)
     * them on first use. Returns an empty list — and never calls the agent —
     * while no document is ready and summarized yet.
     *
     * @return array<int, string>
     */
    public function for(Workspace $workspace): array
    {
        $documents = $workspace->documents()
            ->where('status', Document::STATUS_READY)
            ->whereNotNull('summary')
            ->orderBy('id')
            ->get(['id', 'filename', 'summary']);

        if ($documents->isEmpty()) {
            return [];
        }

        return Cache::remember(
            $this->cacheKey($workspace, $documents),
            self::CACHE_TTL,
            fn (): array => $this->generate($documents),
        );
    }

    /**
     * A cache key scoped to the workspace and the exact summaries in play, so the
     * suggestions regenerate whenever those summaries change.
     *
     * @param  Collection<int, Document>  $documents
     */
    private function cacheKey(Workspace $workspace, Collection $documents): string
    {
        $fingerprint = md5($documents
            ->map(fn (Document $document): string => $document->id.':'.$document->summary)
            ->implode('|'));

        return "workspace:{$workspace->id}:suggestions:{$fingerprint}";
    }

    /**
     * Prompt the agent once with the document summaries and return its questions.
     *
     * @param  Collection<int, Document>  $documents
     * @return array<int, string>
     */
    private function generate(Collection $documents): array
    {
        $summaries = $documents
            ->map(fn (Document $document): string => "- {$document->filename}: {$document->summary}")
            ->implode("\n");

        $response = (new SuggestionAgent)->prompt($summaries, provider: config('rag.text_failover'));

        return (new Collection($response['questions'] ?? []))
            ->map(fn (mixed $question): string => trim((string) $question))
            ->filter()
            ->take(5)
            ->values()
            ->all();
    }
}
