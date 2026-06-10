<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
        <header class="border-b border-zinc-200 dark:border-zinc-700">
            <div class="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-4 py-4">
                <a href="{{ route('home') }}" class="font-semibold text-zinc-900 dark:text-white" wire:navigate>
                    {{ config('app.name', 'Laravel') }}
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

        <main class="mx-auto w-full max-w-3xl px-4 py-8">
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>
