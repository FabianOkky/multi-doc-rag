@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

{{-- The single page-title treatment used across the app: optional eyebrow label,
     a tight display heading, one line of context, and an optional actions slot. --}}
<div {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="flex min-w-0 flex-col gap-1.5">
        @if ($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif

        <h1 class="truncate text-2xl font-semibold text-zinc-900 sm:text-3xl dark:text-zinc-50">
            {{ $title }}
        </h1>

        @if ($description)
            <p class="max-w-2xl text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
                {{ $description }}
            </p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
