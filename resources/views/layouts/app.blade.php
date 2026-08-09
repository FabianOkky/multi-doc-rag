<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="px-0! py-0!">
        <div class="mx-auto w-full max-w-6xl px-5 py-8 sm:px-8 lg:py-12">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
