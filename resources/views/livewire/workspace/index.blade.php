<div class="flex w-full flex-col gap-8">
    <x-page-header
        :eyebrow="__('Library')"
        :title="__('Workspaces')"
        :description="__('Each workspace holds its own documents and its own chat. Nothing is ever retrieved across them.')"
    />

    {{-- Create: a single inline field so the primary action is the first thing
         you can act on, not a modal you have to open first. --}}
    <form wire:submit="createWorkspace" class="panel flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
        <flux:icon name="folder-plus" class="hidden size-5 shrink-0 text-zinc-400 sm:block dark:text-zinc-500" />

        <flux:input
            wire:model="name"
            :placeholder="__('Name a new workspace — e.g. Research papers')"
            class="flex-1"
        />

        <flux:button type="submit" variant="primary" icon="plus" class="shrink-0">
            {{ __('Create') }}
        </flux:button>
    </form>

    @if ($workspaces->isEmpty())
        <div class="panel flex flex-col items-center gap-3 px-6 py-16 text-center">
            <flux:icon name="folder" class="size-7 text-zinc-300 dark:text-zinc-600" />
            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('No workspaces yet') }}</p>
            <p class="max-w-xs text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Create your first one above, then upload the documents you want to question.') }}
            </p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($workspaces as $workspace)
                <div wire:key="workspace-{{ $workspace->id }}" class="panel group relative flex flex-col justify-between gap-6 p-5 transition-colors hover:border-zinc-300 dark:hover:border-zinc-700">
                    <div class="flex flex-col gap-1.5">
                        {{-- Stretched link: the whole card is the target, but the
                             row of buttons below sits above it via z-index. --}}
                        <a href="{{ route('workspaces.show', $workspace) }}" wire:navigate class="after:absolute after:inset-0">
                            <h2 class="text-base font-semibold text-zinc-900 decoration-zinc-300 underline-offset-4 group-hover:underline dark:text-zinc-50 dark:decoration-zinc-600">
                                {{ $workspace->name }}
                            </h2>
                        </a>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Created :time', ['time' => $workspace->created_at->diffForHumans()]) }}
                        </p>
                    </div>

                    <div class="relative z-10 flex items-center gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="startRename({{ $workspace->id }})">
                            {{ __('Rename') }}
                        </flux:button>
                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmDelete({{ $workspace->id }})">
                            {{ __('Delete') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

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
