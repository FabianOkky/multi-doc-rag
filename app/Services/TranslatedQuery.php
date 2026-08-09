<?php

namespace App\Services;

/**
 * A user's question together with the phrasings that should be embedded and
 * searched for it. `variants` always starts with the original question, so a
 * monolingual query behaves exactly like it did before translation existed.
 */
class TranslatedQuery
{
    /**
     * @param  string  $original  The question exactly as the user typed it.
     * @param  string|null  $language  Detected language of the question ("id", "en", "other"), or null when unknown.
     * @param  array<int, string>  $variants  Distinct phrasings to embed, original first.
     */
    public function __construct(
        public string $original,
        public ?string $language = null,
        public array $variants = [],
    ) {
        $this->variants = $this->variants === [] ? [$original] : $this->variants;
    }

    /**
     * Build a query that is searched with the user's wording only — used when
     * multilingual search is disabled or the translation prompt failed.
     */
    public static function monolingual(string $question): self
    {
        return new self($question, null, [$question]);
    }

    /**
     * Build a query from the translation agent's output, dropping blanks and
     * duplicates (a question already in English yields two identical variants).
     *
     * @param  array<int, string|null>  $phrasings
     */
    public static function make(string $question, ?string $language, array $phrasings): self
    {
        $variants = [];

        foreach ([$question, ...$phrasings] as $phrasing) {
            $phrasing = trim((string) $phrasing);

            if ($phrasing === '' || in_array(mb_strtolower($phrasing), array_map(mb_strtolower(...), $variants), true)) {
                continue;
            }

            $variants[] = $phrasing;
        }

        return new self($question, $language, $variants);
    }

    /**
     * Determine whether the question is searched in more than one phrasing.
     */
    public function isMultilingual(): bool
    {
        return count($this->variants) > 1;
    }
}
