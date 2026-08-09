<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950">
        <div class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            {{-- Brand panel: says what the product does before asking for a password. --}}
            <div class="relative hidden flex-col justify-between border-e border-zinc-200 bg-zinc-900 p-10 lg:flex dark:border-zinc-800">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5" wire:navigate>
                    <span class="flex aspect-square size-8 items-center justify-center rounded-lg bg-zinc-50 text-zinc-900">
                        <x-app-logo-icon class="size-5" />
                    </span>
                    <span class="text-sm font-semibold text-zinc-50">{{ config('app.name', 'Multi-Doc RAG') }}</span>
                </a>

                <div class="flex max-w-md flex-col gap-8">
                    <p class="text-3xl leading-snug font-semibold tracking-tight text-zinc-50">
                        {{ __('Upload your documents. Ask anything. Get answers with the receipts.') }}
                    </p>

                    <ul class="flex flex-col gap-4">
                        @foreach ([
                            ['icon' => 'document-text', 'text' => __('PDF, DOCX and TXT, indexed page by page.')],
                            ['icon' => 'link', 'text' => __('Every answer cites the file and page it came from.')],
                            ['icon' => 'language', 'text' => __('Ask in Indonesian, search documents written in English — and the other way round.')],
                        ] as $point)
                            <li class="flex items-start gap-3 text-sm leading-relaxed text-zinc-400">
                                <flux:icon :name="$point['icon']" class="mt-0.5 size-4 shrink-0 text-zinc-500" />
                                {{ $point['text'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="text-xs text-zinc-600">
                    {{ __('Nothing is answered from outside your own documents.') }}
                </p>
            </div>

            {{-- Form panel --}}
            <div class="flex w-full flex-col justify-center px-6 py-12 sm:px-10">
                <div class="mx-auto flex w-full max-w-sm flex-col gap-8">
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5 lg:hidden" wire:navigate>
                        <span class="flex aspect-square size-8 items-center justify-center rounded-lg bg-accent-content text-accent-foreground">
                            <x-app-logo-icon class="size-5" />
                        </span>
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ config('app.name', 'Multi-Doc RAG') }}</span>
                    </a>

                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
