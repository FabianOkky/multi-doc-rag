<?php

namespace App\Livewire;

use App\Models\ChatMessage;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    /**
     * The number of recent records shown in each "latest" panel.
     */
    private const int RECENT_LIMIT = 5;

    public function render(): View
    {
        $user = Auth::user();

        $workspaceIds = $user->workspaces()->pluck('id');

        $documents = Document::query()->whereIn('workspace_id', $workspaceIds);

        $readyDocumentCount = (clone $documents)->where('status', Document::STATUS_READY)->count();

        $stats = [
            [
                'label' => __('Workspaces'),
                'value' => $workspaceIds->count(),
                'hint' => __('Separate spaces for separate projects'),
                'icon' => 'folder',
            ],
            [
                'label' => __('Documents'),
                'value' => $documents->count(),
                'hint' => __(':count ready to query', ['count' => $readyDocumentCount]),
                'icon' => 'document-text',
            ],
            [
                'label' => __('Indexed passages'),
                'value' => DocumentChunk::query()->whereIn('workspace_id', $workspaceIds)->count(),
                'hint' => __('Searchable chunks with citations'),
                'icon' => 'circle-stack',
            ],
            [
                'label' => __('Questions asked'),
                'value' => ChatMessage::query()
                    ->where('role', ChatMessage::ROLE_USER)
                    ->whereHas('chatSession', fn ($query) => $query->whereIn('workspace_id', $workspaceIds))
                    ->count(),
                'hint' => __('Grounded answers, every time'),
                'icon' => 'chat-bubble-left-right',
            ],
        ];

        return view('livewire.dashboard', [
            'stats' => $stats,
            'hasWorkspaces' => $workspaceIds->isNotEmpty(),
            'recentWorkspaces' => $user->workspaces()
                ->withCount('documents')
                ->latest()
                ->take(self::RECENT_LIMIT)
                ->get(),
            'recentDocuments' => Document::query()
                ->whereIn('workspace_id', $workspaceIds)
                ->with('workspace:id,name')
                ->latest()
                ->take(self::RECENT_LIMIT)
                ->get(),
        ]);
    }
}
