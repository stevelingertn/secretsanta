<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ isset($title) ? $title.' | ' : '' }}Admin | {{ $event?->name ?? config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/secret-santa-car-show-logo.webp') }}" type="image/webp">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" x-data="{ nav: false }">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Skip to content</a>

    <header class="border-b-4 border-ink bg-white no-print">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/secret-santa-car-show-logo.webp') }}" alt="" width="382" height="410" class="h-10 w-auto">
                <span class="font-display text-xl font-bold uppercase tracking-wide">Show admin</span>
            </a>
            @if ($event)
                <span class="badge hidden border border-ink sm:inline-flex">{{ $event->status->label() }}</span>
            @endif
            <button type="button" class="btn btn-sm btn-ghost ml-auto lg:hidden" x-on:click="nav = !nav" x-bind:aria-expanded="nav.toString()" aria-controls="admin-nav">Menu</button>
            <form method="POST" action="{{ route('admin.logout') }}" class="ml-auto hidden lg:block">
                @csrf
                <button class="btn btn-sm btn-ghost">Sign out</button>
            </form>
        </div>
        @php
            $links = [
                ['admin.dashboard', 'Dashboard', 'admin.dashboard'],
                ['admin.contestants.index', 'Contestants', 'admin.contestants.*'],
                ['admin.cars.index', 'Cars', 'admin.cars.*'],
                ['admin.paper.index', 'Paper ballots', 'admin.paper.*'],
                ['admin.results.show', 'Results', 'admin.results.*'],
                ['admin.reports.index', 'Reports', 'admin.reports.*'],
                ['admin.votes.index', 'Votes', 'admin.votes.*'],
                ['admin.categories.index', 'Classes', 'admin.categories.*'],
                ['admin.event.edit', 'Event', 'admin.event.*'],
            ];
        @endphp
        <nav id="admin-nav" aria-label="Admin" class="border-t border-line bg-paper lg:block" x-bind:class="nav ? 'block' : 'hidden'">
            <ul class="mx-auto flex max-w-7xl flex-col px-2 lg:flex-row lg:flex-wrap" role="list">
                @foreach ($links as [$route, $label, $pattern])
                    <li>
                        <a href="{{ route($route) }}"
                           @class([
                               'block px-3 py-3 font-display text-lg font-bold uppercase tracking-wide lg:py-2',
                               'bg-ink text-white' => request()->routeIs($pattern),
                               'hover:bg-white' => ! request()->routeIs($pattern),
                           ])
                           @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
                    </li>
                @endforeach
                <li class="lg:ml-auto"><a href="{{ route('gallery') }}" class="block px-3 py-3 font-semibold underline lg:py-2">Public site</a></li>
                <li class="lg:hidden">
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="block w-full px-3 py-3 text-left font-semibold underline">Sign out</button>
                    </form>
                </li>
            </ul>
        </nav>
    </header>

    <main id="main" class="mx-auto max-w-7xl px-4 py-6">
        @if (! $event)
            <div class="mb-4 rounded-lg border-2 border-warn bg-warn-soft px-4 py-3 font-semibold text-warn">No active event. Create one on the Event page.</div>
        @endif
        @if (session('status'))
            <div class="mb-4 rounded-lg border-2 border-ok bg-ok-soft px-4 py-3 font-semibold text-ok" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border-2 border-bad bg-bad-soft px-4 py-3 text-bad" role="alert">
                <ul class="list-disc pl-5">
                    @foreach (array_unique($errors->all()) as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
