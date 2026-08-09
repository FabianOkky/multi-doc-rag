<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => __('Ask your documents')])
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950">
        <header class="border-b border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="flex aspect-square size-8 items-center justify-center rounded-lg bg-accent-content text-accent-foreground">
                        <x-app-logo-icon class="size-5" />
                    </span>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ config('app.name', 'Multi-Doc RAG') }}
                    </span>
                </a>

                <nav class="flex items-center gap-1">
                    @auth
                        <flux:button :href="route('dashboard')" size="sm" variant="primary" icon:trailing="arrow-right">
                            {{ __('Dashboard') }}
                        </flux:button>
                    @else
                        <flux:button :href="route('login')" size="sm" variant="ghost">{{ __('Log in') }}</flux:button>
                        @if (Route::has('register'))
                            <flux:button :href="route('register')" size="sm" variant="primary">{{ __('Get started') }}</flux:button>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main>
            {{-- Hero --}}
            <section class="mx-auto w-full max-w-5xl px-5 pt-16 pb-14 sm:px-8 sm:pt-24 sm:pb-20">
                <div class="flex max-w-3xl flex-col gap-6">
                    <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-zinc-200 px-3 py-1 text-[0.6875rem] font-medium text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        {{ __('Retrieval-augmented, not improvised') }}
                    </span>

                    <h1 class="text-4xl leading-[1.05] font-semibold tracking-tight text-balance text-zinc-900 sm:text-6xl dark:text-zinc-50">
                        {{ __('Ask your documents.') }}
                        <span class="block text-zinc-400 dark:text-zinc-500">{{ __('Get answers with the receipts.') }}</span>
                    </h1>

                    <p class="max-w-xl text-base leading-relaxed text-zinc-600 sm:text-lg dark:text-zinc-300">
                        {{ __('Upload PDFs, Word files and notes into a workspace, then ask anything across all of them. Every answer names the file and page it came from — and nothing else.') }}
                    </p>

                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        @auth
                            <flux:button :href="route('dashboard')" variant="primary" icon:trailing="arrow-right">
                                {{ __('Open your dashboard') }}
                            </flux:button>
                        @else
                            @if (Route::has('register'))
                                <flux:button :href="route('register')" variant="primary" icon:trailing="arrow-right">
                                    {{ __('Create a workspace') }}
                                </flux:button>
                            @endif
                            <flux:button :href="route('login')" variant="ghost">{{ __('Log in') }}</flux:button>
                        @endauth
                    </div>
                </div>
            </section>

            {{-- A sketch of the actual product, so the promise stays concrete. --}}
            <section class="mx-auto w-full max-w-5xl px-5 pb-20 sm:px-8">
                <div class="panel overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                        <flux:icon name="chat-bubble-left-right" class="size-4 text-zinc-400" />
                        <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Research papers') }}</span>
                    </div>

                    <div class="flex flex-col gap-5 p-5 sm:p-8">
                        <div class="flex justify-end">
                            <p class="max-w-[85%] rounded-2xl rounded-br-md bg-zinc-900 px-4 py-2.5 text-sm text-zinc-50 dark:bg-zinc-100 dark:text-zinc-900">
                                {{ __('Berapa anggaran yang diusulkan untuk tahun depan?') }}
                            </p>
                        </div>

                        <div class="flex flex-col items-start gap-2">
                            <p class="max-w-[85%] rounded-2xl rounded-bl-md bg-zinc-100 px-4 py-2.5 text-sm leading-relaxed text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100">
                                {{ __('The proposed budget for the next fiscal year is 4.2 million, up 8% year on year, with the increase concentrated in infrastructure.') }}
                            </p>

                            <div class="flex flex-wrap items-center gap-1.5 ps-1">
                                <span class="eyebrow">{{ __('Sources') }}</span>
                                @foreach ([['annual-report.pdf', 12], ['budget-notes.docx', 3]] as [$file, $page])
                                    <span class="inline-flex items-center gap-1 rounded-md border border-zinc-200 bg-white px-2 py-0.5 text-[0.6875rem] text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                        <flux:icon name="document-text" class="size-3 text-zinc-400" />
                                        {{ $file }}
                                        <span class="text-zinc-400 dark:text-zinc-500">{{ __('p.') }} {{ $page }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <p class="rule pt-4 text-xs text-zinc-400 dark:text-zinc-500">
                            {{ __('Asked in Indonesian, answered from English sources — the question is searched in both languages.') }}
                        </p>
                    </div>
                </div>
            </section>

            {{-- What it actually does --}}
            <section class="border-y border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mx-auto grid w-full max-w-5xl gap-px bg-zinc-200 sm:grid-cols-3 dark:bg-zinc-800">
                    @foreach ([
                        ['icon' => 'document-magnifying-glass', 'title' => __('Grounded, or silent'), 'body' => __('Answers are assembled from passages retrieved out of your own files. If the answer is not in them, it says so instead of guessing.')],
                        ['icon' => 'link', 'title' => __('Citations you can check'), 'body' => __('Each reply lists the filename and page number behind it, taken from the exact passages that were retrieved.')],
                        ['icon' => 'language', 'title' => __('Bilingual by design'), 'body' => __('Every question is searched in both Indonesian and English, so the language of your notes never limits what you can find.')],
                    ] as $feature)
                        <div class="flex flex-col gap-3 bg-white p-8 dark:bg-zinc-900">
                            <flux:icon :name="$feature['icon']" class="size-5 text-zinc-400 dark:text-zinc-500" />
                            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ $feature['title'] }}</h2>
                            <p class="text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $feature['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- How it works --}}
            <section class="mx-auto w-full max-w-5xl px-5 py-20 sm:px-8">
                <div class="flex flex-col gap-10">
                    <div class="flex flex-col gap-2">
                        <p class="eyebrow">{{ __('How it works') }}</p>
                        <h2 class="max-w-lg text-2xl font-semibold tracking-tight text-zinc-900 sm:text-3xl dark:text-zinc-50">
                            {{ __('Three steps, then it is just a conversation.') }}
                        </h2>
                    </div>

                    <ol class="grid gap-8 sm:grid-cols-3">
                        @foreach ([
                            ['n' => '01', 'title' => __('Create a workspace'), 'body' => __('One space per project. Documents and chat never cross between them.')],
                            ['n' => '02', 'title' => __('Upload your documents'), 'body' => __('PDF, DOCX or TXT. Each file is parsed page by page, summarized, and indexed.')],
                            ['n' => '03', 'title' => __('Ask anything'), 'body' => __('Questions run across every document at once, and the reply cites its sources.')],
                        ] as $step)
                            <li class="flex flex-col gap-2 border-t border-zinc-200 pt-5 dark:border-zinc-800">
                                <span class="font-mono text-xs text-zinc-400 dark:text-zinc-500">{{ $step['n'] }}</span>
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ $step['title'] }}</h3>
                                <p class="text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $step['body'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            {{-- Close --}}
            @guest
                <section class="mx-auto w-full max-w-5xl px-5 pb-24 sm:px-8">
                    <div class="panel flex flex-col items-center gap-5 px-6 py-14 text-center">
                        <h2 class="max-w-lg text-2xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-3xl dark:text-zinc-50">
                            {{ __('Put your first document in and ask it something.') }}
                        </h2>
                        <p class="max-w-md text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
                            {{ __('No setup beyond an account. Upload a file, wait for it to index, and start asking.') }}
                        </p>
                        @if (Route::has('register'))
                            <flux:button :href="route('register')" variant="primary" icon:trailing="arrow-right">
                                {{ __('Get started') }}
                            </flux:button>
                        @endif
                    </div>
                </section>
            @endguest
        </main>

        <footer class="border-t border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto flex w-full max-w-5xl flex-col gap-2 px-5 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <p class="text-xs text-zinc-400 dark:text-zinc-500">
                    {{ config('app.name', 'Multi-Doc RAG') }}
                </p>
                <p class="text-xs text-zinc-400 dark:text-zinc-500">
                    {{ __('Answers are only ever drawn from the documents you upload.') }}
                </p>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
