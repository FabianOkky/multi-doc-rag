<div class="flex h-[32rem] w-full flex-col overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
    {{-- Conversation --}}
    <div class="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
        @if ($messages->isEmpty())
            <div class="flex flex-1 flex-col items-center justify-center gap-2 text-center">
                <flux:icon name="chat-bubble-left-right" class="size-8 text-zinc-400 dark:text-zinc-500" />
                <flux:text>{{ __('Ask anything about your documents — answers cite their source.') }}</flux:text>
            </div>
        @endif

        @include('livewire.chat.partials.messages', ['messages' => $messages])

        {{-- The assistant reply streams in here while it is being generated. --}}
        @if ($pendingQuestion !== null)
            <div class="flex flex-col items-start gap-2" wire:key="pending-answer">
                <div class="max-w-[80%] rounded-2xl bg-zinc-100 px-4 py-2 text-sm whitespace-pre-line text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100">
                    <span wire:stream="answer"></span>
                    <flux:icon name="ellipsis-horizontal" class="inline size-5 animate-pulse text-zinc-400" wire:loading wire:target="streamAnswer" />
                </div>
            </div>
        @endif
    </div>

    <div class="border-t border-zinc-200 dark:border-zinc-700">
        {{-- Suggested questions: starter chips shown on a fresh conversation. The
             wrapper carries wire:init so the suggestions load even before any
             exist; clicking a chip sends it through the normal chat flow. --}}
        @if ($messages->isEmpty())
            <div wire:init="loadSuggestions" class="flex flex-col gap-2 px-3 pt-3">
                @if (! empty($suggestions))
                    <flux:text class="text-xs text-zinc-500">{{ __('Not sure where to start? Try one of these:') }}</flux:text>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($suggestions as $index => $suggestion)
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="sparkles"
                                wire:click="askSuggestion({{ $index }})"
                                wire:loading.attr="disabled"
                                wire:target="askSuggestion"
                            >
                                {{ $suggestion }}
                            </flux:button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Composer --}}
        <form wire:submit="sendMessage" class="flex items-end gap-2 p-3">
            <flux:input
                class="flex-1"
                wire:model="question"
                :placeholder="__('Ask a question about your documents…')"
                autocomplete="off"
            />
            <flux:button
                type="submit"
                variant="primary"
                icon="paper-airplane"
                wire:loading.attr="disabled"
                wire:target="sendMessage"
            >
                {{ __('Send') }}
            </flux:button>
        </form>
    </div>
</div>
