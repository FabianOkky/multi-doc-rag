<?php

namespace App\Services;

/**
 * The language an agent should write in. Retrieval is always cross-lingual (see
 * QueryTranslator), so this only controls the language of the *output* — the
 * chat answer, a document summary, or the suggested starter questions.
 *
 * Auto lets the model mirror its input, which is what most users expect; the
 * explicit cases exist because smaller/cheaper models write noticeably better
 * English than Indonesian, so a user may prefer an English answer to an
 * Indonesian question.
 */
enum AnswerLanguage: string
{
    case Auto = 'auto';

    case Indonesian = 'id';

    case English = 'en';

    /**
     * The human-readable label shown in the language picker.
     */
    public function label(): string
    {
        return match ($this) {
            self::Auto => __('Match my question'),
            self::Indonesian => __('Bahasa Indonesia'),
            self::English => __('English'),
        };
    }

    /**
     * The instruction line telling a chat agent which language to answer in.
     */
    public function answerDirective(): string
    {
        return match ($this) {
            self::Auto => 'Write your answer in the same language the user asked in, even when the supporting passages are in another language.',
            self::Indonesian => 'Write your answer in Bahasa Indonesia, no matter which language the question or the passages are in.',
            self::English => 'Write your answer in English, no matter which language the question or the passages are in.',
        };
    }

    /**
     * A short reminder appended to the user's turn when a language is forced.
     *
     * The system prompt alone loses to the conversation history: after a few
     * Indonesian exchanges the model keeps answering in Indonesian however the
     * instructions are worded. Repeating the requirement immediately before the
     * model replies is the position that actually wins. Null for Auto, which
     * needs no reminder — mirroring the question is already the default.
     */
    public function turnReminder(): ?string
    {
        return match ($this) {
            self::Auto => null,
            self::Indonesian => 'Jawab dalam Bahasa Indonesia.',
            self::English => 'Answer in English.',
        };
    }

    /**
     * The instruction line telling a summarizing agent which language to write in.
     */
    public function summaryDirective(): string
    {
        return match ($this) {
            self::Auto => 'Write it in the same language as the document itself.',
            self::Indonesian => 'Write it in Bahasa Indonesia, whatever language the document is in.',
            self::English => 'Write it in English, whatever language the document is in.',
        };
    }

    /**
     * All cases keyed by value, for building selects and validation rules.
     *
     * @return array<string, self>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case;
        }

        return $options;
    }

    /**
     * Resolve a (possibly untrusted or missing) value, falling back to Auto.
     */
    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Auto;
    }
}
