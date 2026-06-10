<?php

namespace App\Livewire\Chat;

use App\Agents\DocumentChatAgent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Workspace;
use App\Services\Retriever;
use App\Services\Suggester;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Window extends Component
{
    use AuthorizesRequests;

    /**
     * How many prior messages to replay to the agent as conversation history.
     */
    private const int HISTORY_LIMIT = 10;

    public Workspace $workspace;

    public ChatSession $session;

    #[Validate('required|string|max:2000')]
    public string $question = '';

    /**
     * The question awaiting an answer: set when the user sends a message and
     * consumed by streamAnswer() so the reply can stream into the UI.
     */
    public ?string $pendingQuestion = null;

    /**
     * Suggested starter questions for this workspace, shown as clickable chips.
     * Loaded lazily (see loadSuggestions) so the page paints before the agent runs.
     *
     * @var array<int, string>
     */
    public array $suggestions = [];

    public function mount(Workspace $workspace): void
    {
        // Defense in depth: the page already authorizes the owner, but the chat
        // never operates on a workspace the current user cannot view.
        $this->authorize('view', $workspace);

        $this->workspace = $workspace;
        $this->session = ChatSession::firstOrCreate(['workspace_id' => $workspace->id]);
    }

    /**
     * Populate the suggested-question chips from the workspace's ready documents.
     * Deferred (wire:init) so it never blocks the first paint, and cached by the
     * Suggester so the agent is prompted at most once per set of summaries.
     */
    public function loadSuggestions(): void
    {
        $this->suggestions = app(Suggester::class)->for($this->workspace);
    }

    /**
     * Send a suggested question through the normal chat flow as if the user had
     * typed it, reusing sendMessage() so there is a single send path.
     */
    public function askSuggestion(int $index): void
    {
        $question = $this->suggestions[$index] ?? null;

        if ($question === null) {
            return;
        }

        $this->question = $question;

        $this->sendMessage();
    }

    /**
     * Persist the user's question, then ask streamAnswer() to produce the reply.
     */
    public function sendMessage(): void
    {
        $this->validate();

        $this->session->messages()->create([
            'role' => ChatMessage::ROLE_USER,
            'content' => $this->question,
        ]);

        $this->pendingQuestion = $this->question;
        $this->question = '';

        $this->dispatch('chat-answer-requested');
    }

    /**
     * Retrieve grounding context, stream the agent's answer to the UI, and save
     * the assistant message with the citations of the chunks that were used.
     */
    #[On('chat-answer-requested')]
    public function streamAnswer(): void
    {
        $question = $this->pendingQuestion;

        if ($question === null) {
            return;
        }

        $this->pendingQuestion = null;

        $result = app(Retriever::class)->retrieve($this->workspace, $question);

        $agent = new DocumentChatAgent($this->workspace, $result->context, $this->history());

        $answer = '';

        foreach ($agent->stream($question, provider: config('rag.text_failover')) as $event) {
            if ($event instanceof TextDelta) {
                $answer .= $event->delta;
                $this->stream(to: 'answer', content: $event->delta);
            }
        }

        $this->session->messages()->create([
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => $answer,
            'citations' => $result->citations,
        ]);
    }

    /**
     * The recent conversation history, oldest first, excluding the just-asked
     * question (the agent appends that itself via stream($question)).
     *
     * @return Collection<int, ChatMessage>
     */
    private function history(): Collection
    {
        $messages = $this->session->messages()
            ->latest('id')
            ->limit(self::HISTORY_LIMIT + 1)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isNotEmpty() && $messages->last()->role === ChatMessage::ROLE_USER) {
            $messages = $messages->slice(0, -1)->values();
        }

        return $messages;
    }

    public function render(): View
    {
        return view('livewire.chat.window', [
            'messages' => $this->session->messages()->oldest('id')->get(),
        ]);
    }
}
