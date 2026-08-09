<?php

namespace App\Services;

use App\Agents\SuggestionAgent;
use App\Models\Document;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

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

        $language = $workspace->answerLanguage();
        $key = $this->cacheKey($workspace, $documents, $language);

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $suggestions = $this->generate($documents, $language);

        // Only a real answer is worth remembering: caching a failed prompt would
        // hide the chips for a whole day over one rate-limited minute.
        if ($suggestions !== []) {
            Cache::put($key, $suggestions, self::CACHE_TTL);
        }

        return $suggestions;
    }

    /**
     * A cache key scoped to the workspace, the exact summaries in play, and the
     * language they should be asked in, so the suggestions regenerate whenever
     * those summaries or that language change.
     *
     * @param  Collection<int, Document>  $documents
     */
    private function cacheKey(Workspace $workspace, Collection $documents, AnswerLanguage $language): string
    {
        $fingerprint = md5($documents
            ->map(fn (Document $document): string => $document->id.':'.$document->summary)
            ->implode('|'));

        return "workspace:{$workspace->id}:suggestions:{$language->value}:{$fingerprint}";
    }

    /**
     * Prompt the agent once with the document summaries and return its questions,
     * or an empty list if the provider is unavailable.
     *
     * Starter chips are a convenience, never the point of the page: a rate-limited
     * provider (or a fallback model that cannot produce structured output) must
     * cost the user their suggestions, not their whole workspace.
     *
     * @param  Collection<int, Document>  $documents
     * @return array<int, string>
     */
    private function generate(Collection $documents, AnswerLanguage $language): array
    {
        $summaries = $documents
            ->map(fn (Document $document): string => "- {$document->filename}: {$document->summary}")
            ->implode("\n");

        try {
            $response = (new SuggestionAgent($language))->prompt($summaries, provider: config('rag.text_failover'));
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return (new Collection($response['questions'] ?? []))
            ->map(fn (mixed $question): string => trim((string) $question))
            ->filter()
            ->take(5)
            ->values()
            ->all();
    }
}
