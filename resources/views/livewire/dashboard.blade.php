<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</flux:heading>
            <flux:text>{{ __('Upload your documents, ask anything — every answer is grounded in your sources, with citations.') }}</flux:text>
        </div>

        <flux:button :href="route('workspaces.index')" wire:navigate variant="primary" icon="plus" class="shrink-0">
            {{ __('New workspace') }}
        </flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($stats as $stat)
            <flux:card wire:key="stat-{{ $loop->index }}" class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <flux:text class="text-sm">{{ $stat['label'] }}</flux:text>
                    <span class="flex size-9 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-700/60 dark:text-zinc-300">
                        <flux:icon :name="$stat['icon']" class="size-5" />
                    </span>
                </div>
                <flux:heading size="xl" class="tabular-nums">{{ number_format($stat['value']) }}</flux:heading>
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $stat['hint'] }}</flux:text>
            </flux:card>
        @endforeach
    </div>

    @if ($hasWorkspaces)
        <div class="grid gap-4 lg:grid-cols-2">
            <flux:card class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-4">
                    <flux:heading size="lg">{{ __('Recent workspaces') }}</flux:heading>
                    <flux:button :href="route('workspaces.index')" wire:navigate size="sm" variant="ghost" icon:trailing="arrow-right">
                        {{ __('View all') }}
                    </flux:button>
                </div>

                <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-700">
                    @foreach ($recentWorkspaces as $workspace)
                        <a
                            href="{{ route('workspaces.show', $workspace) }}"
                            wire:navigate
                            wire:key="recent-workspace-{{ $workspace->id }}"
                            class="group flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-700/60 dark:text-zinc-300">
                                    <flux:icon name="folder" class="size-5" />
                                </span>
                                <div class="flex min-w-0 flex-col">
                                    <flux:heading size="sm" class="truncate group-hover:underline">{{ $workspace->name }}</flux:heading>
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ trans_choice('{0}No documents|{1}:count document|[2,*]:count documents', $workspace->documents_count, ['count' => $workspace->documents_count]) }}
                                        · {{ $workspace->created_at->diffForHumans() }}
                                    </flux:text>
                                </div>
                            </div>
                            <flux:icon name="chevron-right" class="size-4 shrink-0 text-zinc-400" />
                        </a>
                    @endforeach
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Recent documents') }}</flux:heading>

                @if ($recentDocuments->isEmpty())
                    <div class="flex flex-col items-center gap-2 py-8 text-center">
                        <flux:icon name="document-text" class="size-7 text-zinc-400 dark:text-zinc-500" />
                        <flux:text class="text-sm">{{ __('No documents yet. Open a workspace to upload your first file.') }}</flux:text>
                    </div>
                @else
                    <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-700">
                        @foreach ($recentDocuments as $document)
                            <a
                                href="{{ route('workspaces.show', $document->workspace_id) }}"
                                wire:navigate
                                wire:key="recent-document-{{ $document->id }}"
                                class="group flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <flux:icon name="document-text" class="size-5 shrink-0 text-zinc-400 dark:text-zinc-500" />
                                    <div class="flex min-w-0 flex-col">
                                        <flux:heading size="sm" class="truncate group-hover:underline">{{ $document->filename }}</flux:heading>
                                        <flux:text class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $document->workspace?->name }}
                                        </flux:text>
                                    </div>
                                </div>

                                @if ($document->status === \App\Models\Document::STATUS_READY)
                                    <flux:badge color="green" size="sm" icon="check-circle">{{ __('Ready') }}</flux:badge>
                                @elseif ($document->status === \App\Models\Document::STATUS_FAILED)
                                    <flux:badge color="red" size="sm" icon="exclamation-triangle">{{ __('Failed') }}</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm" icon="arrow-path">{{ __('Processing') }}</flux:badge>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </div>
    @else
        <flux:card class="flex flex-col items-center gap-6 py-12 text-center">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-500 dark:bg-zinc-700/60 dark:text-zinc-300">
                <flux:icon name="sparkles" class="size-7" />
            </span>

            <div class="flex max-w-md flex-col gap-1">
                <flux:heading size="lg">{{ __('Get started in three steps') }}</flux:heading>
                <flux:text>{{ __('Create a workspace, upload your documents, then ask questions and get answers cited to the exact page.') }}</flux:text>
            </div>

            <div class="grid w-full max-w-2xl gap-4 sm:grid-cols-3">
                <div class="flex flex-col items-center gap-2">
                    <flux:icon name="folder-plus" class="size-6 text-zinc-500 dark:text-zinc-300" />
                    <flux:heading size="sm">{{ __('1. Create a workspace') }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One space per project or topic.') }}</flux:text>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <flux:icon name="arrow-up-tray" class="size-6 text-zinc-500 dark:text-zinc-300" />
                    <flux:heading size="sm">{{ __('2. Upload documents') }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('PDF, DOCX, or TXT — up to 20MB each.') }}</flux:text>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <flux:icon name="chat-bubble-left-right" class="size-6 text-zinc-500 dark:text-zinc-300" />
                    <flux:heading size="sm">{{ __('3. Ask anything') }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Answers cite the source filename and page.') }}</flux:text>
                </div>
            </div>

            <flux:button :href="route('workspaces.index')" wire:navigate variant="primary" icon="plus">
                {{ __('Create your first workspace') }}
            </flux:button>
        </flux:card>
    @endif
</div>
