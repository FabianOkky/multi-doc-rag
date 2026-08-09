<?php

namespace App\Agents;

use App\Services\AnswerLanguage;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Summarizes an uploaded document into a few factual sentences, grounded only
 * in the supplied text. Uses the configured default provider/model
 * (Gemini gemini-2.5-flash) — exactly one prompt per document to save quota.
 *
 * The summary follows the workspace's answer language, so a workspace set to
 * English reads consistently even when its documents are Indonesian.
 */
class SummaryAgent implements Agent
{
    use Promptable;

    public function __construct(public AnswerLanguage $language = AnswerLanguage::Auto) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $language = $this->language->summaryDirective();

        return <<<INSTRUCTIONS
            Summarize the given document in 3-4 clear, factual sentences. Use ONLY information
            present in the document text — do not add, over-interpret, or invent anything.
            Write one paragraph of plain text: no markdown, no heading, no bullet points.
            {$language}
            INSTRUCTIONS;
    }
}
