<div class="flex w-full flex-col gap-10">
    <div class="flex flex-col gap-3">
        <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-zinc-200 px-2.5 py-1 text-[0.6875rem] font-medium text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            <flux:icon name="eye" class="size-3" />
            {{ __('Read-only shared view') }}
        </span>

        <h1 class="text-2xl font-semibold text-zinc-900 sm:text-3xl dark:text-zinc-50">{{ $workspace->name }}</h1>

        <p class="max-w-2xl text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
            {{ __('A shared workspace. Browse its documents and the conversation, grounded in real sources.') }}
        </p>
    </div>

    <section class="flex flex-col gap-3">
        <div class="flex items-baseline justify-between gap-4">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Documents') }}</h2>
            <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $documents->count() }}</span>
        </div>

        @forelse ($documents as $document)
            <article wire:key="document-{{ $document->id }}" class="panel flex flex-col gap-3 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-2.5">
                        <flux:icon name="document-text" class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                        <div class="flex min-w-0 flex-col">
                            <h3 class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $document->filename }}</h3>
                            <span class="font-mono text-[0.6875rem] uppercase text-zinc-400 dark:text-zinc-500">{{ $document->file_type }}</span>
                        </div>
                    </div>

                    <x-document-status :status="$document->status" />
                </div>

                @if ($document->summary)
                    <p class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $document->summary }}</p>
                @endif
            </article>
        @empty
            <div class="panel flex flex-col items-center gap-2 px-4 py-12 text-center">
                <flux:icon name="document-text" class="size-6 text-zinc-300 dark:text-zinc-600" />
                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('No documents yet') }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('This workspace has no documents to show.') }}</p>
            </div>
        @endforelse
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Conversation') }}</h2>

        <div class="panel flex w-full flex-col gap-5 p-5">
            @if ($messages->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                    <flux:icon name="chat-bubble-left-right" class="size-6 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No conversation yet.') }}</p>
                </div>
            @endif

            @include('livewire.chat.partials.messages', ['messages' => $messages])
        </div>
    </section>
</div>
