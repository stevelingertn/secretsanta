@extends('layouts.public', ['title' => 'Cars'])

@section('content')
<div x-data="tallies(@js(route('tallies')), @js($refreshedAt->inShowTz()->format('g:i A')))">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-4xl uppercase sm:text-5xl">The cars</h1>
            <p class="mt-1 text-muted">
                {{ $cars->total() }} {{ Str::plural('car', $cars->total()) }}
                @if ($event->isVotingOpen()) &middot; <span class="font-semibold text-ok">Voting is open</span>
                @elseif ($event->status === \App\Enums\EventStatus::Setup) &middot; Voting has not opened yet
                @else &middot; <span class="font-semibold">Voting is closed</span>
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2 text-sm text-muted" aria-live="polite">
            <span x-show="!failed">Vote counts as of <span class="font-semibold text-ink" x-text="refreshedAt">{{ $refreshedAt->inShowTz()->format('g:i A') }}</span></span>
            <span x-cloak x-show="failed" class="font-semibold text-bad">Could not refresh. Showing counts from <span x-text="refreshedAt"></span>.</span>
            <button type="button" class="btn btn-sm btn-ghost" x-on:click="refresh()" x-bind:disabled="loading">
                <span x-text="loading ? 'Refreshing' : 'Refresh'">Refresh</span>
            </button>
        </div>
    </div>

    @if ($event->isVotingOpen())
        @guest
            <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border-2 border-ink bg-white p-4">
                <p class="flex-1 font-semibold">Entered a car? Sign in with the login code on your ballot to vote.</p>
                <a href="{{ route('login') }}" class="btn btn-primary">Sign in to vote</a>
            </div>
        @endguest
    @elseif ($event->status === \App\Enums\EventStatus::Finalized)
        <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border-2 border-ink bg-brand-yellow p-4">
            <p class="flex-1 font-semibold">Results are final.</p>
            <a href="{{ route('results') }}" class="btn btn-ghost">See the winners</a>
        </div>
    @endif

    <form method="GET" action="{{ route('gallery') }}" class="mt-5 grid gap-3 sm:grid-cols-[1fr_16rem_auto]" role="search">
        <div>
            <label for="q" class="sr-only">Search by car number or vehicle</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="field" placeholder="Car number, year, make or model" inputmode="search" autocomplete="off">
        </div>
        <div>
            <label for="class" class="sr-only">Class</label>
            <select id="class" name="class" class="field" onchange="this.form.submit()">
                <option value="">All classes</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['class'] ?? null) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-secondary flex-1 sm:flex-none">Search</button>
            @if (! empty($filters['q']) || ! empty($filters['class']))
                <a href="{{ route('gallery') }}" class="btn btn-ghost">Clear</a>
            @endif
        </div>
    </form>

    @if ($cars->isEmpty())
        <div class="card mt-6 p-8 text-center">
            <p class="text-lg font-semibold">No cars match that search.</p>
            <a href="{{ route('gallery') }}" class="mt-3 inline-block underline">Show all cars</a>
        </div>
    @else
        <ul class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" role="list">
            @foreach ($cars as $car)
                <li>
                    <a href="{{ route('cars.show', $car->entry_number) }}" class="card group block overflow-hidden transition-shadow hover:shadow-lg focus-visible:shadow-lg">
                        <div class="relative">
                            <x-car-photo :car="$car" />
                            <span class="plate absolute left-3 top-3 shadow" aria-label="Car number {{ $car->entry_number }}">{{ $car->entry_number }}</span>
                        </div>
                        <div class="flex items-start gap-3 p-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-display text-2xl font-bold leading-tight">{{ $car->description }}</p>
                                <p class="mt-1 text-muted">{{ $car->category->name }}</p>
                            </div>
                            <p class="shrink-0 text-right">
                                <span class="block font-display text-3xl font-extrabold tabular-nums leading-none" data-tally="{{ $car->entry_number }}">{{ $car->votes_count }}</span>
                                <span class="text-sm text-muted" data-tally-label>{{ Str::plural('vote', $car->votes_count) }}</span>
                            </p>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $cars->links() }}</div>
    @endif
</div>
@endsection
