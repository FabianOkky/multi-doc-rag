<?php

namespace App\Agents;

use App\Models\ChatMessage;
use App\Models\Workspace;
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
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $context = trim($this->context) !== ''
            ? $this->context
            : '(Tidak ada konteks relevan yang ditemukan di dokumen workspace ini.)';

        return <<<INSTRUCTIONS
            Anda adalah asisten yang menjawab pertanyaan HANYA berdasarkan KONTEKS dokumen di bawah.
            Aturan:
            - Jawab semata-mata dari KONTEKS. Jangan memakai pengetahuan di luar konteks dan jangan mengarang.
            - Jika jawaban tidak ada di KONTEKS, katakan dengan jujur bahwa informasinya tidak ditemukan
              di dokumen — jangan menebak.
            - Selalu sebutkan sumber yang Anda pakai dengan menyebut nama file dan nomor halaman, persis
              seperti label sumber pada KONTEKS (mis. "menurut laporan.pdf hal. 3").
            - Jawab ringkas dan jelas, dalam bahasa yang sama dengan pertanyaan pengguna.

            KONTEKS:
            {$context}
            INSTRUCTIONS;
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
