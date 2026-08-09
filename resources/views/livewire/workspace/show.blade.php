<div class="flex w-full flex-col gap-8">
    <div class="flex flex-col gap-5">
        <a href="{{ route('workspaces.index') }}" wire:navigate class="inline-flex w-fit items-center gap-1.5 text-xs font-medium text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
            <flux:icon name="arrow-left" class="size-3.5" />
            {{ __('Workspaces') }}
        </a>

        <x-page-header
            :eyebrow="__('Workspace')"
            :title="$workspace->name"
            :description="__('Upload documents and chat with them, grounded in your sources.')"
        >
            <x-slot name="actions">
                <flux:modal.trigger name="workspace-settings">
                    <flux:button size="sm" variant="ghost" icon="cog-6-tooth">{{ __('Share') }}</flux:button>
                </flux:modal.trigger>
            </x-slot>
        </x-page-header>
    </div>

    {{-- Documents on the left, the conversation on the right: the chat is the
         reason the workspace exists, so on a wide screen it stays in view while
         you scroll the file list. --}}
    <div class="grid items-start gap-6 lg:grid-cols-5">
        <div class="flex flex-col gap-6 lg:col-span-2">
            {{-- Upload --}}
            <form wire:submit="save" class="panel flex flex-col gap-4 p-5">
                <div class="flex flex-col gap-1">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Upload documents') }}</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('PDF, DOCX, or TXT — up to 20MB each.') }}</p>
                </div>

                <label class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-dashed border-zinc-300 px-4 py-8 text-center transition-colors hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-800/50">
                    <flux:icon name="arrow-up-tray" class="size-5 text-zinc-400 dark:text-zinc-500" />
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Choose files') }}</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('or drop them here') }}</span>

                    <input
                        type="file"
                        wire:model="files"
                        multiple
                        accept=".pdf,.docx,.txt"
                        class="sr-only"
                    />
                </label>

                @if ($files)
                    <ul class="flex flex-col gap-1">
                        @foreach ($files as $file)
                            <li wire:key="pending-{{ $loop->index }}" class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-300">
                                <flux:icon name="paper-clip" class="size-3.5 shrink-0 text-zinc-400" />
                                <span class="truncate">{{ $file->getClientOriginalName() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @foreach ($errors->all() as $message)
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @endforeach

                <div class="flex items-center gap-3">
                    <flux:button type="submit" size="sm" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="save,files">
                        {{ __('Upload') }}
                    </flux:button>
                    <span class="text-xs text-zinc-500" wire:loading wire:target="save,files">{{ __('Working…') }}</span>
                </div>
            </form>

            {{-- Documents --}}
            <section @if ($isProcessing) wire:poll.5s @endif class="flex flex-col gap-3">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Documents') }}</h2>
                    <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $documents->count() }}</span>
                </div>

                @forelse ($documents as $document)
                    <article wire:key="document-{{ $document->id }}" class="panel group flex flex-col gap-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-2.5">
                                <flux:icon name="document-text" class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                                <div class="flex min-w-0 flex-col">
                                    <h3 class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $document->filename }}</h3>
                                    <span class="font-mono text-[0.6875rem] uppercase text-zinc-400 dark:text-zinc-500">{{ $document->file_type }}</span>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-document-status :status="$document->status" />

                                <button
                                    type="button"
                                    wire:click="deleteDocument({{ $document->id }})"
                                    wire:confirm="{{ __('Delete this document? This cannot be undone.') }}"
                                    class="rounded p-1 text-zinc-400 opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100 hover:text-rose-600 dark:hover:text-rose-400"
                                >
                                    <flux:icon name="trash" class="size-4" />
                                    <span class="sr-only">{{ __('Delete document') }}</span>
                                </button>
                            </div>
                        </div>

                        @if ($document->status === \App\Models\Document::STATUS_READY)
                            @if ($document->summary)
                                <p class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $document->summary }}</p>
                            @endif
                        @elseif ($document->status === \App\Models\Document::STATUS_FAILED)
                            <div class="flex flex-col items-start gap-2">
                                <p class="text-xs text-rose-600 dark:text-rose-400">
                                    {{ __('We could not process this document. Retry, or delete it and upload again.') }}
                                </p>
                                <flux:button
                                    size="xs"
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
                            <div class="flex flex-col gap-1.5">
                                <div class="h-2 w-3/4 animate-pulse rounded bg-zinc-200 dark:bg-zinc-800" aria-hidden="true"></div>
                                <div class="h-2 w-1/2 animate-pulse rounded bg-zinc-200 dark:bg-zinc-800" aria-hidden="true"></div>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Generating summary…') }}</p>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="panel flex flex-col items-center gap-2 px-4 py-10 text-center">
                        <flux:icon name="document-text" class="size-6 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('No documents yet') }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Upload your first document above to get started.') }}</p>
                    </div>
                @endforelse
            </section>
        </div>

        {{-- Pinned to the viewport once you scroll past it, so the composer stays
             reachable however long the document list gets. --}}
        <div class="lg:sticky lg:top-6 lg:col-span-3 lg:h-[calc(100vh-3rem)]">
            <livewire:chat.window :workspace="$workspace" :key="'chat-'.$workspace->id" />
        </div>
    </div>

    {{-- Sharing lives in a modal: it is a one-off decision, not something you
         need to look at every time you open the workspace. --}}
    <flux:modal name="workspace-settings" class="max-w-lg">
        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">{{ __('Share') }}</flux:heading>
                <flux:text class="text-sm">
                    {{ __('Anyone with the link can view this workspace read-only — no sign-in needed.') }}
                </flux:text>
            </div>

            <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Public link') }}</span>
                <flux:switch wire:model.live="isShared" />
            </div>

            @if ($isShared && $workspace->share_token)
                <flux:input
                    readonly
                    copyable
                    icon="link"
                    :value="route('workspaces.shared', $workspace->share_token)"
                />
            @endif
        </div>
    </flux:modal>
</div>
