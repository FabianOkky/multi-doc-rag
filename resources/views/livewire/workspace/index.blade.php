<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">{{ __('Workspaces') }}</flux:heading>
        <flux:text>{{ __('Each workspace holds its own documents and chat.') }}</flux:text>
    </div>

    <form wire:submit="createWorkspace">
        <div class="flex items-start gap-3">
            <flux:input
                wire:model="name"
                :label="__('New workspace')"
                :placeholder="__('e.g. Research papers')"
                class="flex-1"
            />
            <flux:button type="submit" variant="primary" icon="plus" class="mt-6 shrink-0">
                {{ __('Create') }}
            </flux:button>
        </div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($workspaces as $workspace)
            <flux:card wire:key="workspace-{{ $workspace->id }}" class="flex flex-col gap-4">
                <a href="{{ route('workspaces.show', $workspace) }}" wire:navigate class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ $workspace->name }}</flux:heading>
                    <flux:text class="text-sm">
                        {{ __('Created :time', ['time' => $workspace->created_at->diffForHumans()]) }}
                    </flux:text>
                </a>

                <div class="flex gap-2">
                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="startRename({{ $workspace->id }})">
                        {{ __('Rename') }}
                    </flux:button>
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmDelete({{ $workspace->id }})">
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            </flux:card>
        @empty
            <flux:text class="col-span-full">
                {{ __('No workspaces yet. Create your first one above.') }}
            </flux:text>
        @endforelse
    </div>

    <flux:modal wire:model="showEditModal" class="max-w-md">
        <form wire:submit="rename" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Rename workspace') }}</flux:heading>
            <flux:input wire:model="editName" :label="__('Name')" />
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Delete workspace?') }}</flux:heading>
            <flux:text>
                {{ __('":name" and everything inside it will be permanently deleted.', ['name' => $deletingName]) }}
            </flux:text>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" icon="trash" wire:click="deleteWorkspace">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
