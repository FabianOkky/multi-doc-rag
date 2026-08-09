@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1.5">
    <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ $title }}</h1>
    <p class="text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
</div>
