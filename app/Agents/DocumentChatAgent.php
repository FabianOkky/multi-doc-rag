<?php

namespace App\Agents;

use App\Models\ChatMessage;
use App\Models\Workspace;
use App\Services\AnswerLanguage;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Answers a user's question strictly from the document chunks retrieved for the
 * workspace. The relevant context is gathered up-front (scoped per-workspace) and
 * injected as $context; the agent must never answer from outside it and always
 * cites its sources by filename and page. Uses the configured default
 * provider/model (Gemini gemini-2.5-flash).
 *
 * The instructions are written in English on purpose: the models this app targets
 * follow English system prompts more reliably than Indonesian ones. Which language
 * the *answer* is written in is a separate, explicit decision ($answerLanguage).
 */
class DocumentChatAgent implements Agent, Conversational
{
    use Promptable;

    /**
     * @param  iterable<int, ChatMessage>  $history  Prior turns of the conversation, oldest first.
     */
    public function __construct(
        public Workspace $workspace,
        public string $context,
        public iterable $history = [],
        public AnswerLanguage $answerLanguage = AnswerLanguage::Auto,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $context = trim($this->context) !== ''
            ? $this->context
            : '(No relevant passage was found in this workspace.)';

        $language = $this->answerLanguage->answerDirective();

        // The language rule comes last, after the context. Earlier turns of the
        // conversation are replayed to the model and pull hard towards the
        // language they were written in, so a directive buried in a bullet list
        // above a wall of retrieved text loses to them.
        return <<<INSTRUCTIONS
            You answer questions about a set of documents the user uploaded, using ONLY the
            CONTEXT below. Each passage is preceded by its source label in square brackets.

            Rules:
            - Answer strictly from the CONTEXT. Never use outside knowledge and never invent
              facts, numbers, or quotes.
            - The CONTEXT and the question are often in different languages (usually Bahasa
              Indonesia and English). Translate across languages as needed: an English passage
              fully answers an Indonesian question, and the other way round.
            - If the CONTEXT does not contain the answer, say so plainly and stop. Do not guess,
              and do not fill the gap from general knowledge.
            - Always name the sources you used, reproducing their source label exactly as it
              appears in the CONTEXT, for example "(laporan.pdf, page 3)". Never cite a source
              that is not in the CONTEXT.
            - Be concise and concrete. Prefer a short paragraph or a few bullets over an essay.

            CONTEXT:
            {$context}

            LANGUAGE OF YOUR REPLY — this overrides the language of everything above,
            including the earlier messages in this conversation:
            {$language}
            INSTRUCTIONS;
    }

    /**
     * Build the prompt for this turn: the user's question, plus a one-line
     * language reminder when an answer language is forced.
     *
     * The stored ChatMessage keeps the user's own wording — only what is sent to
     * the model carries the reminder.
     */
    public function turn(string $question): string
    {
        $reminder = $this->answerLanguage->turnReminder();

        return $reminder === null ? $question : $question."\n\n[{$reminder}]";
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return (new Collection($this->history))
            ->map(fn (ChatMessage $message): Message => new Message($message->role, $message->content))
            ->all();
    }
}
