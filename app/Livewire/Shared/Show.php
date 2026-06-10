<?php

namespace App\Livewire\Shared;

use App\Models\ChatMessage;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class Show extends Component
{
    public Workspace $workspace;

    /**
     * Resolve the workspace from its public share token. Anything that is not a
     * currently-shared workspace — an unknown token or one whose owner turned
     * sharing off — is a 404, so disabled links leak nothing.
     */
    public function mount(string $token): void
    {
        $workspace = Workspace::query()
            ->where('share_token', $token)
            ->where('is_shared', true)
            ->first();

        abort_if($workspace === null, 404);

        $this->workspace = $workspace;
    }

    public function render(): View
    {
        return view('livewire.shared.show', [
            'documents' => $this->workspace->documents()->latest()->get(),
            'messages' => $this->conversation(),
        ])->title($this->workspace->name);
    }

    /**
     * The workspace's conversation, oldest first, shown read-only to visitors.
     *
     * @return Collection<int, ChatMessage>
     */
    private function conversation(): Collection
    {
        $session = $this->workspace->chatSessions()->first();

        if ($session === null) {
            return collect();
        }

        return $session->messages()->oldest('id')->get();
    }
}
