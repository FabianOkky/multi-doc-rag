{{-- Renders the conversation bubbles for a collection of $messages. Shared by
     the live chat window and the read-only shared workspace view. --}}
@foreach ($messages as $message)
    @if ($message->role === \App\Models\ChatMessage::ROLE_USER)
        <div class="flex justify-end">
            <div class="max-w-[80%] rounded-2xl bg-zinc-900 px-4 py-2 text-sm whitespace-pre-line text-white dark:bg-white dark:text-zinc-900">
                {{ $message->content }}
            </div>
        </div>
    @else
        <div class="flex flex-col items-start gap-2">
            <div class="max-w-[80%] rounded-2xl bg-zinc-100 px-4 py-2 text-sm whitespace-pre-line text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100">
                {{ $message->content }}
            </div>

            @if (! empty($message->citations))
                <div class="flex flex-wrap gap-2">
                    @foreach ($message->citations as $citation)
                        <flux:badge size="sm" color="zinc" icon="document-text">
                            {{ $citation['filename'] ?? __('Document') }}@if (! empty($citation['page_number'])) · {{ __('hal.') }} {{ $citation['page_number'] }}@endif
                        </flux:badge>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
@endforeach
