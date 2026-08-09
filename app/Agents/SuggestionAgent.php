<?php

namespace App\Agents;

use App\Services\AnswerLanguage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Proposes a handful of starter questions a user could ask about a workspace,
 * grounded only in the per-document summaries it is given (never the full text).
 * Returns the questions as structured output (a plain list of strings) and uses
 * the configured default provider/model (Gemini gemini-2.5-flash) — exactly one
 * prompt per workspace to save quota.
 *
 * The questions follow the workspace's answer language so the chips match the
 * language the user will be answered in.
 */
class SuggestionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(public AnswerLanguage $language = AnswerLanguage::Auto) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $language = match ($this->language) {
            AnswerLanguage::Auto => 'Write the questions in the same language as the summaries.',
            AnswerLanguage::Indonesian => 'Write the questions in Bahasa Indonesia, whatever language the summaries are in.',
            AnswerLanguage::English => 'Write the questions in English, whatever language the summaries are in.',
        };

        return <<<INSTRUCTIONS
            You help someone start a conversation about their own documents. From the document
            summaries you are given, write 3-5 short questions that CAN be answered from those
            documents. Each question must be specific, tied to something the summaries actually
            mention, and end with a question mark. Use ONLY what is in the summaries — never
            invent a topic the documents do not cover.
            {$language}
            INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()
                ->items($schema->string())
                ->min(3)
                ->max(5)
                ->description('3-5 suggested questions that can be answered from the documents.')
                ->required(),
        ];
    }
}
