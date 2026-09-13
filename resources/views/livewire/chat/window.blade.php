<div class="panel flex h-152 w-full flex-col overflow-hidden lg:h-full lg:min-h-128">
    {{-- Header: what this panel is, and which language it will answer in. --}}
    <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
        <div class="flex min-w-0 items-center gap-2">
            <flux:icon name="chat-bubble-left-right" class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
            <h2 class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Chat') }}</h2>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <flux:tooltip :content="__('Ask in any language — this only changes the language of the reply.')" position="bottom">
                <span class="hidden text-xs text-zinc-500 sm:inline dark:text-zinc-400">{{ __('Reply in') }}</span>
            </flux:tooltip>

            <flux:select wire:model.live="answerLanguage" size="sm" class="w-auto min-w-38">
                @foreach ($languages as $value => $language)
                    <flux:select.option value="{{ $value }}">{{ $language->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Conversation --}}
    <div x-data="chatTranscript" class="scrollbar-subtle flex flex-1 flex-col gap-5 overflow-y-auto px-4 py-5">
        @if ($messages->isEmpty())
            <div class="flex flex-1 flex-col items-center justify-center gap-2 px-6 text-center">
                <flux:icon name="chat-bubble-left-right" class="size-6 text-zinc-300 dark:text-zinc-600" />
                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Ask anything about your documents') }}</p>
                <p class="max-w-xs text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                    {{ __('Every answer cites the file and page it came from. Indonesian and English both work — even when your documents are in the other one.') }}
                </p>
            </div>
        @endif

        @include('livewire.chat.partials.messages', ['messages' => $messages])

        {{-- The assistant reply streams in here while it is being generated. --}}
        @if ($pendingQuestion !== null)
            <div class="flex flex-col items-start gap-2" wire:key="pending-answer">
                <div class="max-w-[85%] rounded-2xl rounded-bl-md bg-zinc-100 px-4 py-2.5 text-sm leading-relaxed whitespace-pre-line text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100">
                    <span wire:stream="answer"></span>
                    <flux:icon name="ellipsis-horizontal" class="inline size-5 animate-pulse text-zinc-400" wire:loading wire:target="streamAnswer" />
                </div>
            </div>
        @endif
    </div>

    <div class="border-t border-zinc-200 dark:border-zinc-800">
        {{-- Suggested questions: starter chips shown on a fresh conversation. The
             wrapper carries wire:init so the suggestions load even before any
             exist; clicking a chip sends it through the normal chat flow. --}}
        @if ($messages->isEmpty())
            <div wire:init="loadSuggestions" class="flex flex-col gap-2 px-4 pt-4">
                @if (! empty($suggestions))
                    <p class="eyebrow">{{ __('Not sure where to start?') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($suggestions as $index => $suggestion)
                            <button
                                type="button"
                                wire:click="askSuggestion({{ $index }})"
                                wire:loading.attr="disabled"
                                wire:target="askSuggestion"
                                class="rounded-full border border-zinc-200 px-3 py-1.5 text-left text-xs text-zinc-600 transition-colors hover:border-zinc-400 hover:text-zinc-900 disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-500 dark:hover:text-zinc-50"
                            >
                                {{ $suggestion }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Composer --}}
        <form wire:submit="sendMessage" class="flex items-center gap-2 p-3">
            <flux:input
                class="flex-1"
                wire:model="question"
                wire:loading.attr="disabled"
                wire:target="sendMessage,streamAnswer"
                :placeholder="__('Ask a question about your documents…')"
                autocomplete="off"
            />
            <flux:button
                type="submit"
                variant="primary"
                icon="paper-airplane"
                wire:loading.attr="disabled"
                wire:target="sendMessage,streamAnswer"
            >
                <span class="sr-only sm:not-sr-only">{{ __('Send') }}</span>
            </flux:button>
        </form>
    </div>
</div>
