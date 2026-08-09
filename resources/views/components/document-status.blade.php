@props([
    'status',
])

{{-- A document's processing state, shown as a dot + label rather than a coloured
     pill: statuses appear next to each other in long lists, and a row of badges
     shouts louder than the filenames it is describing. --}}
@php
    $states = [
        \App\Models\Document::STATUS_READY => ['dot' => 'bg-emerald-500', 'text' => 'text-zinc-500 dark:text-zinc-400', 'label' => __('Ready')],
        \App\Models\Document::STATUS_FAILED => ['dot' => 'bg-rose-500', 'text' => 'text-rose-600 dark:text-rose-400', 'label' => __('Failed')],
    ];

    $state = $states[$status] ?? ['dot' => 'bg-amber-500 animate-pulse', 'text' => 'text-zinc-500 dark:text-zinc-400', 'label' => __('Processing')];
@endphp

<span {{ $attributes->class('inline-flex shrink-0 items-center gap-1.5 text-xs font-medium '.$state['text']) }}>
    <span class="size-1.5 rounded-full {{ $state['dot'] }}"></span>
    {{ $state['label'] }}
</span>
