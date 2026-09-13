<?php

namespace App\Services;

use App\Agents\QueryTranslationAgent;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Turns a question into the set of phrasings retrieval should search for, so an
 * Indonesian question can find English source material and vice versa.
 *
 * Translation costs one small prompt, so results are cached per distinct
 * question: asking the same thing twice (or clicking the same suggestion chip)
 * is free. Any failure degrades to searching the user's own wording — a slightly
 * worse result is always better than a broken chat.
 */
class QueryTranslator
{
    /**
     * How long a translated question stays cached (seconds).
     */
    private const int CACHE_TTL = 604800;

    /**
     * Keep this aligned with the chat input limit so every accepted question can
     * receive the same Indonesian/English retrieval treatment promised by the UI.
     */
    private const int MAX_TRANSLATABLE_CHARS = 2000;

    /**
     * Get the phrasings to search for the given question.
     */
    public function translate(string $question): TranslatedQuery
    {
        $question = trim($question);

        if (! config('rag.multilingual_query') || mb_strlen($question) > self::MAX_TRANSLATABLE_CHARS) {
            return TranslatedQuery::monolingual($question);
        }

        $translation = Cache::remember(
            $this->cacheKey($question),
            self::CACHE_TTL,
            fn (): ?array => $this->prompt($question),
        );

        if ($translation === null) {
            return TranslatedQuery::monolingual($question);
        }

        return TranslatedQuery::make($question, $translation['language'] ?? null, [
            $translation['indonesian'] ?? null,
            $translation['english'] ?? null,
        ]);
    }

    /**
     * A cache key scoped to the normalized question text.
     */
    private function cacheKey(string $question): string
    {
        return 'rag:query-translation:'.sha1(mb_strtolower($question));
    }

    /**
     * Prompt the agent for the question's language and both phrasings, or null
     * when the provider is unavailable (no key, quota exhausted, timeout) or
     * answered in a shape we cannot read.
     *
     * @return array{language: string|null, indonesian: string|null, english: string|null}|null
     */
    private function prompt(string $question): ?array
    {
        try {
            $response = (new QueryTranslationAgent)->prompt($question, provider: config('rag.text_failover'));

            return $this->parse($response->text);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Read the agent's three labelled lines. Both phrasings are required: with
     * either one missing there is nothing to gain over searching the question
     * as the user wrote it, so the caller degrades instead of half-translating.
     *
     * @return array{language: string|null, indonesian: string|null, english: string|null}|null
     */
    private function parse(string $text): ?array
    {
        $fields = [];

        foreach (preg_split('/\R/', trim($text)) ?: [] as $line) {
            if (preg_match('/^\s*(LANG|ID|EN)\s*:\s*(.+?)\s*$/i', $line, $matches) === 1) {
                $fields[strtoupper($matches[1])] = trim($matches[2], " \t\"'");
            }
        }

        if (($fields['ID'] ?? '') === '' || ($fields['EN'] ?? '') === '') {
            return null;
        }

        $language = strtolower($fields['LANG'] ?? '');

        return [
            'language' => in_array($language, ['id', 'en', 'other'], true) ? $language : null,
            'indonesian' => $fields['ID'],
            'english' => $fields['EN'],
        ];
    }
}
