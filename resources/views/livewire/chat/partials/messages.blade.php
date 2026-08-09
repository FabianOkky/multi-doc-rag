{{-- Renders the conversation bubbles for a collection of $messages. Shared by
     the live chat window and the read-only shared workspace view. --}}
@foreach ($messages as $message)
    @if ($message->role === \App\Models\ChatMessage::ROLE_USER)
        <div class="flex justify-end" wire:key="message-{{ $message->id }}">
            <div class="max-w-[85%] rounded-2xl rounded-br-md bg-zinc-900 px-4 py-2.5 text-sm leading-relaxed whitespace-pre-line text-zinc-50 dark:bg-zinc-100 dark:text-zinc-900">
                {{ $message->content }}
            </div>
        </div>
    @else
        <div class="flex flex-col items-start gap-2" wire:key="message-{{ $message->id }}">
            <div class="max-w-[85%] rounded-2xl rounded-bl-md bg-zinc-100 px-4 py-2.5 text-sm leading-relaxed whitespace-pre-line text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100">
                {{ $message->content }}
            </div>

            @if (! empty($message->citations))
                {{-- Sources are the point of the product, so they are listed
                     explicitly under the answer rather than hidden behind it. --}}
                <div class="flex flex-wrap items-center gap-1.5 ps-1">
                    <span class="eyebrow">{{ __('Sources') }}</span>
                    @foreach ($message->citations as $citation)
                        <span class="inline-flex max-w-full items-center gap-1 rounded-md border border-zinc-200 bg-white px-2 py-0.5 text-[0.6875rem] text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                            <flux:icon name="document-text" class="size-3 shrink-0 text-zinc-400" />
                            <span class="truncate">{{ $citation['filename'] ?? __('Document') }}</span>
                            @if (! empty($citation['page_number']))
                                <span class="shrink-0 text-zinc-400 dark:text-zinc-500">{{ __('p.') }} {{ $citation['page_number'] }}</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
@endforeach
