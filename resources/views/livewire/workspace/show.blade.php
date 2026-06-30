<div class="flex w-full flex-col gap-6">
    <div>
        <flux:button :href="route('workspaces.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left">
            {{ __('Workspaces') }}
        </flux:button>
    </div>

    <div class="flex flex-col gap-1">
        <flux:heading size="xl">{{ $workspace->name }}</flux:heading>
        <flux:text>{{ __('Upload documents and chat with them, grounded in your sources.') }}</flux:text>
    </div>

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Upload documents') }}</flux:heading>
            <flux:text class="text-sm">{{ __('PDF, DOCX, or TXT — up to 20MB each.') }}</flux:text>

            <input
                type="file"
                wire:model="files"
                multiple
                accept=".pdf,.docx,.txt"
                class="block w-full text-sm text-zinc-600 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:text-zinc-300 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600"
            />

            @foreach ($errors->all() as $message)
                <flux:text class="text-sm text-red-500">{{ $message }}</flux:text>
            @endforeach

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="save,files">
                    {{ __('Upload') }}
                </flux:button>
                <flux:text class="text-sm" wire:loading wire:target="save,files">
                    {{ __('Working…') }}
                </flux:text>
            </div>
        </form>
    </flux:card>

    <flux:card>
        <div class="flex flex-col gap-4">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Share') }}</flux:heading>
                    <flux:text class="text-sm">{{ __('Anyone with the link can view this workspace read-only — no sign-in needed.') }}</flux:text>
                </div>
                <flux:switch wire:model.live="isShared" />
            </div>

            @if ($isShared && $workspace->share_token)
                <flux:input
                    readonly
                    copyable
                    icon="link"
                    :label="__('Public link')"
                    :value="route('workspaces.shared', $workspace->share_token)"
                />
            @endif
        </div>
    </flux:card>

    <div @if ($isProcessing) wire:poll.5s @endif class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Documents') }}</flux:heading>

        @forelse ($documents as $document)
            <flux:card wire:key="document-{{ $document->id }}" class="flex flex-col gap-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <flux:icon name="document-text" class="size-6 shrink-0 text-zinc-400 dark:text-zinc-500" />
                        <div class="flex flex-col">
                            <flux:heading size="sm">{{ $document->filename }}</flux:heading>
                            <flux:text class="text-xs uppercase">{{ $document->file_type }}</flux:text>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($document->status === \App\Models\Document::STATUS_READY)
                            <flux:badge color="green" icon="check-circle">{{ __('Ready') }}</flux:badge>
                        @elseif ($document->status === \App\Models\Document::STATUS_FAILED)
                            <flux:badge color="red" icon="exclamation-triangle">{{ __('Failed') }}</flux:badge>
                        @else
                            <flux:badge color="amber" icon="arrow-path">{{ __('Processing') }}</flux:badge>
                        @endif

                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="trash"
                            wire:click="deleteDocument({{ $document->id }})"
                            wire:confirm="{{ __('Delete this document? This cannot be undone.') }}"
                        >
                            <span class="sr-only">{{ __('Delete document') }}</span>
                        </flux:button>
                    </div>
                </div>

                @if ($document->status === \App\Models\Document::STATUS_READY)
                    @if ($document->summary)
                        <flux:text class="text-sm">{{ $document->summary }}</flux:text>
                    @endif
                @elseif ($document->status === \App\Models\Document::STATUS_FAILED)
                    <div class="flex flex-col items-start gap-3">
                        <flux:text class="text-sm text-red-500">
                            {{ __('We could not process this document. Retry, or delete it and upload again.') }}
                        </flux:text>
                        <flux:button
                            size="sm"
                            variant="filled"
                            icon="arrow-path"
                            wire:click="retryDocument({{ $document->id }})"
                            wire:loading.attr="disabled"
                            wire:target="retryDocument"
                        >
                            {{ __('Retry') }}
                        </flux:button>
                    </div>
                @else
                    <div class="flex flex-col gap-2" aria-hidden="true">
                        <div class="h-3 w-3/4 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="h-3 w-1/2 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>
                    <flux:text class="text-xs text-zinc-500">{{ __('Generating summary…') }}</flux:text>
                @endif
            </flux:card>
        @empty
            <flux:card class="flex flex-col items-center gap-2 py-12 text-center">
                <flux:icon name="document-text" class="size-8 text-zinc-400 dark:text-zinc-500" />
                <flux:heading size="lg">{{ __('No documents yet') }}</flux:heading>
                <flux:text>{{ __('Upload your first document above to get started.') }}</flux:text>
            </flux:card>
        @endforelse
    </div>

    <div class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Chat') }}</flux:heading>
        <livewire:chat.window :workspace="$workspace" :key="'chat-'.$workspace->id" />
    </div>
</div>
