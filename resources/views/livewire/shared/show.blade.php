<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-1">
        <flux:badge size="sm" color="zinc" icon="eye" class="self-start">{{ __('Read-only shared view') }}</flux:badge>
        <flux:heading size="xl">{{ $workspace->name }}</flux:heading>
        <flux:text>{{ __('A shared workspace. Browse its documents and the conversation, grounded in real sources.') }}</flux:text>
    </div>

    <div class="flex flex-col gap-4">
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

                    @if ($document->status === \App\Models\Document::STATUS_READY)
                        <flux:badge color="green" icon="check-circle">{{ __('Ready') }}</flux:badge>
                    @elseif ($document->status === \App\Models\Document::STATUS_FAILED)
                        <flux:badge color="red" icon="exclamation-triangle">{{ __('Failed') }}</flux:badge>
                    @else
                        <flux:badge color="amber" icon="arrow-path">{{ __('Processing') }}</flux:badge>
                    @endif
                </div>

                @if ($document->summary)
                    <flux:text class="text-sm">{{ $document->summary }}</flux:text>
                @endif
            </flux:card>
        @empty
            <flux:card class="flex flex-col items-center gap-2 py-12 text-center">
                <flux:icon name="document-text" class="size-8 text-zinc-400 dark:text-zinc-500" />
                <flux:heading size="lg">{{ __('No documents yet') }}</flux:heading>
                <flux:text>{{ __('This workspace has no documents to show.') }}</flux:text>
            </flux:card>
        @endforelse
    </div>

    <div class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Conversation') }}</flux:heading>

        <div class="flex w-full flex-col gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            @if ($messages->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 py-8 text-center">
                    <flux:icon name="chat-bubble-left-right" class="size-8 text-zinc-400 dark:text-zinc-500" />
                    <flux:text>{{ __('No conversation yet.') }}</flux:text>
                </div>
            @endif

            @include('livewire.chat.partials.messages', ['messages' => $messages])
        </div>
    </div>
</div>
