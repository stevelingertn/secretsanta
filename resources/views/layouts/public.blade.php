<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111111">
    <title>{{ isset($title) ? $title.' | ' : '' }}{{ $event?->name ?? config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/secret-santa-car-show-logo.webp') }}" type="image/webp">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Skip to content</a>

    <header class="bg-ink text-white no-print">
        <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3">
            <a href="{{ route('gallery') }}" class="flex min-w-0 items-center gap-3">
                <span class="shrink-0 rounded-lg bg-white p-1">
                    <img src="{{ asset('images/secret-santa-car-show-logo.webp') }}" alt="Secret Santa Car Show logo" width="382" height="410" class="h-12 w-auto sm:h-14">
                </span>
                <span class="min-w-0">
                    <span class="line-clamp-2 block font-display text-lg font-bold uppercase leading-tight tracking-wide sm:truncate sm:text-2xl">{{ $event?->name ?? 'Secret Santa Car Show' }}</span>
                    @if ($event)
                        <span class="block truncate text-sm text-white/75">{{ $event->location ?? 'Oakwood, Georgia' }}@if ($event->show_date) &middot; {{ $event->show_date->format('F j, Y') }}@endif</span>
                    @endif
                </span>
            </a>
            <nav class="ml-auto flex shrink-0 items-center gap-2" aria-label="Main">
                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm bg-white text-ink hover:bg-paper">Admin</a>
                    @else
                        <a href="{{ route('ballot.index') }}" class="btn btn-sm btn-primary">My ballot</a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn btn-sm btn-primary"><span class="sm:hidden">Sign in</span><span class="hidden sm:inline">Contestant sign in</span></a>
                @endauth
            </nav>
        </div>
        <div class="checker"></div>
    </header>

    @if ($event?->is_test)
        <div class="bg-brand-yellow px-4 py-2 text-center text-sm font-semibold text-ink no-print">
            Test show using the {{ $event->year }} entry list. These cars are not confirmed entrants for an upcoming show.
        </div>
    @endif

    <main id="main" class="mx-auto max-w-6xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-lg border-2 border-ok bg-ok-soft px-4 py-3 font-semibold text-ok" role="status">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="mt-10 no-print">
        <div class="checker-thin"></div>
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-5 text-sm text-muted">
            <span>Secret Santa Car Show &middot; Oakwood, Georgia</span>
            <span class="flex gap-4">
                <a class="underline" href="{{ route('gallery') }}">Cars</a>
                <a class="underline" href="{{ route('results') }}">Results</a>
            </span>
        </div>
    </footer>
</body>
</html>
