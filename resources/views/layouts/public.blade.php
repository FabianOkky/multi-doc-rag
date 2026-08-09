<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950">
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex w-full max-w-4xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5" wire:navigate>
                    <span class="flex aspect-square size-7 items-center justify-center rounded-lg bg-accent-content text-accent-foreground">
                        <x-app-logo-icon class="size-4" />
                    </span>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ config('app.name', 'Multi-Doc RAG') }}
                    </span>
                </a>

                @auth
                    <flux:button :href="route('dashboard')" size="sm" variant="ghost" icon:trailing="arrow-right" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:button>
                @else
                    <flux:button :href="route('login')" size="sm" variant="ghost">
                        {{ __('Log in') }}
                    </flux:button>
                @endauth
            </div>
        </header>

        <main class="mx-auto w-full max-w-4xl px-5 py-10 sm:px-8">
            {{ $slot }}
        </main>

        <footer class="mx-auto w-full max-w-4xl px-5 pb-10 sm:px-8">
            <p class="rule pt-6 text-xs text-zinc-400 dark:text-zinc-500">
                {{ __('Shared from :app — answers here are grounded in the documents above.', ['app' => config('app.name', 'Multi-Doc RAG')]) }}
            </p>
        </footer>

        @fluxScripts
    </body>
</html>
