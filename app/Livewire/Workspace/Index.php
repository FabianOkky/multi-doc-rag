<?php

namespace App\Livewire\Workspace;

use App\Models\Workspace;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Workspaces')]
class Index extends Component
{
    use AuthorizesRequests;

    #[Validate('required|string|max:255')]
    public string $name = '';

    public bool $showEditModal = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $editName = '';

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    /**
     * Create a new workspace owned by the current user.
     */
    public function createWorkspace(): void
    {
        $this->authorize('create', Workspace::class);

        $validated = $this->validateOnly('name');

        Auth::user()->workspaces()->create($validated);

        $this->reset('name');

        Flux::toast(variant: 'success', text: __('Workspace created.'));
    }

    /**
     * Open the rename modal for the given workspace.
     */
    public function startRename(int $id): void
    {
        $workspace = Workspace::findOrFail($id);

        $this->authorize('update', $workspace);

        $this->editingId = $workspace->id;
        $this->editName = $workspace->name;
        $this->showEditModal = true;
    }

    /**
     * Persist the new name for the workspace being edited.
     */
    public function rename(): void
    {
        $workspace = Workspace::findOrFail($this->editingId);

        $this->authorize('update', $workspace);

        $validated = $this->validateOnly('editName');

        $workspace->update(['name' => $validated['editName']]);

        $this->reset('editingId', 'editName', 'showEditModal');

        Flux::toast(variant: 'success', text: __('Workspace renamed.'));
    }

    /**
     * Open the delete confirmation modal for the given workspace.
     */
    public function confirmDelete(int $id): void
    {
        $workspace = Workspace::findOrFail($id);

        $this->authorize('delete', $workspace);

        $this->deletingId = $workspace->id;
        $this->deletingName = $workspace->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the workspace currently pending confirmation.
     */
    public function deleteWorkspace(): void
    {
        $workspace = Workspace::findOrFail($this->deletingId);

        $this->authorize('delete', $workspace);

        $workspace->delete();

        $this->reset('deletingId', 'deletingName', 'showDeleteModal');

        Flux::toast(variant: 'success', text: __('Workspace deleted.'));
    }

    public function render(): View
    {
        return view('livewire.workspace.index', [
            'workspaces' => Auth::user()->workspaces()->latest()->get(),
        ]);
    }
}
