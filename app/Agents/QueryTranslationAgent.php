<?php

namespace App\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Rewrites a user's question into both Indonesian and English so retrieval can
 * match documents written in either language.
 *
 * Embeddings are only weakly cross-lingual: an Indonesian question embedded on
 * its own often misses an English passage that answers it perfectly. Searching
 * with both phrasings and merging the hits (see QueryTranslator + Retriever)
 * fixes that without re-indexing or translating the documents themselves, so
 * every citation still points at the user's own text.
 *
 * This returns three labelled lines rather than structured output on purpose.
 * It sits on the chat hot path, so it must survive the whole text failover chain
 * — and the OpenAI-compatible fallbacks (e.g. Groq's llama-3.3-70b) reject
 * `json_schema` requests outright, which would turn a rate-limited Gemini into a
 * hard 400 instead of a working answer. Three lines every model can produce.
 */
class QueryTranslationAgent implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You prepare search queries for a bilingual (Indonesian / English) document
            search engine. You are given one user question. Do NOT answer it.

            Reply with exactly three lines, in this order, and nothing else:
            LANG: <id, en, or other — the language the question was written in>
            ID: <the question in natural Bahasa Indonesia>
            EN: <the question in natural English>

            Rules:
            - Translate meaning, not words. Keep proper nouns, product names, acronyms,
              code identifiers, numbers, and units exactly as written.
            - Keep each line a single question of roughly the original length. Do not add
              context, do not expand abbreviations, do not explain.
            - If the question is already in one of the two languages, repeat it verbatim
              on that line rather than paraphrasing it.
            - No markdown, no quotes around the questions, no extra lines.
            INSTRUCTIONS;
    }
}
