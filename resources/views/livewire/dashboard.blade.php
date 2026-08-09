<div class="flex w-full flex-col gap-10">
    <x-page-header
        :eyebrow="__('Overview')"
        :title="__('Welcome back, :name', ['name' => auth()->user()->name])"
        :description="__('Upload your documents, ask anything — every answer is grounded in your sources, with citations.')"
    >
        <x-slot name="actions">
            <flux:button :href="route('workspaces.index')" wire:navigate variant="primary" icon="plus">
                {{ __('New workspace') }}
            </flux:button>
        </x-slot>
    </x-page-header>

    {{-- Stats read as one ruled strip rather than four floating cards, so the
         numbers line up and the page starts calm. --}}
    <div class="panel overflow-hidden">
        {{-- gap-px over a tinted parent draws exact hairlines however the grid wraps. --}}
        <div class="grid gap-px bg-zinc-200 sm:grid-cols-2 lg:grid-cols-4 dark:bg-zinc-800">
            @foreach ($stats as $stat)
                <div wire:key="stat-{{ $loop->index }}" class="flex flex-col gap-1 bg-white p-5 dark:bg-zinc-900">
                    <div class="flex items-center gap-2">
                        <flux:icon :name="$stat['icon']" class="size-4 text-zinc-400 dark:text-zinc-500" />
                        <span class="eyebrow">{{ $stat['label'] }}</span>
                    </div>
                    <p class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                        {{ number_format($stat['value']) }}
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $stat['hint'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    @if ($hasWorkspaces)
        <div class="grid gap-8 lg:grid-cols-2">
            <section class="flex flex-col gap-4">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Recent workspaces') }}</h2>
                    <a href="{{ route('workspaces.index') }}" wire:navigate class="text-xs font-medium text-zinc-500 underline decoration-zinc-300 underline-offset-4 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:decoration-zinc-600 dark:hover:text-zinc-100">
                        {{ __('View all') }}
                    </a>
                </div>

                <div class="panel divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($recentWorkspaces as $workspace)
                        <a
                            href="{{ route('workspaces.show', $workspace) }}"
                            wire:navigate
                            wire:key="recent-workspace-{{ $workspace->id }}"
                            class="group flex items-center justify-between gap-3 px-4 py-3.5 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                        >
                            <div class="flex min-w-0 flex-col gap-0.5">
                                <span class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $workspace->name }}</span>
                                <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ trans_choice('{0}No documents|{1}:count document|[2,*]:count documents', $workspace->documents_count, ['count' => $workspace->documents_count]) }}
                                    · {{ $workspace->created_at->diffForHumans() }}
                                </span>
                            </div>
                            <flux:icon name="arrow-right" class="size-4 shrink-0 text-zinc-300 transition-transform group-hover:translate-x-0.5 group-hover:text-zinc-500 dark:text-zinc-600" />
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Recent documents') }}</h2>

                @if ($recentDocuments->isEmpty())
                    <div class="panel flex flex-col items-center gap-2 px-6 py-12 text-center">
                        <flux:icon name="document-text" class="size-6 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('No documents yet. Open a workspace to upload your first file.') }}
                        </p>
                    </div>
                @else
                    <div class="panel divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($recentDocuments as $document)
                            <a
                                href="{{ route('workspaces.show', $document->workspace_id) }}"
                                wire:navigate
                                wire:key="recent-document-{{ $document->id }}"
                                class="flex items-center justify-between gap-3 px-4 py-3.5 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <flux:icon name="document-text" class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                                    <div class="flex min-w-0 flex-col gap-0.5">
                                        <span class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $document->filename }}</span>
                                        <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $document->workspace?->name }}</span>
                                    </div>
                                </div>

                                <x-document-status :status="$document->status" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    @else
        {{-- First run: the three steps, laid out as a numbered path. --}}
        <div class="panel overflow-hidden">
            <div class="flex flex-col gap-2 border-b border-zinc-200 px-6 py-8 text-center dark:border-zinc-800">
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Get started in three steps') }}</h2>
                <p class="mx-auto max-w-md text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
                    {{ __('Create a workspace, upload your documents, then ask questions and get answers cited to the exact page.') }}
                </p>
            </div>

            <div class="grid gap-px bg-zinc-200 sm:grid-cols-3 dark:bg-zinc-800">
                @foreach ([
                    ['n' => '01', 'icon' => 'folder-plus', 'title' => __('Create a workspace'), 'body' => __('One space per project or topic.')],
                    ['n' => '02', 'icon' => 'arrow-up-tray', 'title' => __('Upload documents'), 'body' => __('PDF, DOCX, or TXT — up to 20MB each.')],
                    ['n' => '03', 'icon' => 'chat-bubble-left-right', 'title' => __('Ask anything'), 'body' => __('Answers cite the source filename and page.')],
                ] as $step)
                    <div wire:key="step-{{ $step['n'] }}" class="flex flex-col gap-2 bg-white p-6 dark:bg-zinc-900">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs text-zinc-400 dark:text-zinc-500">{{ $step['n'] }}</span>
                            <flux:icon :name="$step['icon']" class="size-4 text-zinc-400 dark:text-zinc-500" />
                        </div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $step['title'] }}</h3>
                        <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-center border-t border-zinc-200 px-6 py-6 dark:border-zinc-800">
                <flux:button :href="route('workspaces.index')" wire:navigate variant="primary" icon="plus">
                    {{ __('Create your first workspace') }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
