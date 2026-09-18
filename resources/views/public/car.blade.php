@extends('layouts.public', ['title' => 'Car #'.$car->entry_number])

@section('content')
<p class="mb-4"><a href="{{ url()->previous() === url()->current() ? route('gallery') : url()->previous() }}" class="font-semibold underline">Back to all cars</a></p>

<article class="card overflow-hidden lg:grid lg:grid-cols-[3fr_2fr]" x-data="tallies(@js(route('tallies')), @js($refreshedAt->inShowTz()->format('g:i A')))">
    <x-car-photo :car="$car" size="full" class="lg:aspect-auto lg:h-full" />
    <div class="p-5 sm:p-6">
        <div class="flex items-center gap-4">
            <span class="plate plate-lg" aria-hidden="true">{{ $car->entry_number }}</span>
            <div>
                <p class="font-display text-lg font-bold uppercase tracking-wide text-muted">Car #{{ $car->entry_number }}</p>
                <h1 class="text-3xl sm:text-4xl">{{ $car->description }}</h1>
            </div>
        </div>

        <dl class="mt-6 grid grid-cols-2 gap-4">
            <div class="rounded-lg bg-paper p-4">
                <dt class="text-sm font-semibold uppercase tracking-wide text-muted">Class</dt>
                <dd class="mt-1 font-display text-2xl font-bold">{{ $car->category->name }}</dd>
            </div>
            <div class="rounded-lg bg-paper p-4">
                <dt class="text-sm font-semibold uppercase tracking-wide text-muted">Contestant votes</dt>
                <dd class="mt-1 font-display text-4xl font-extrabold tabular-nums" data-tally="{{ $car->entry_number }}">{{ $car->votes_count }}</dd>
            </div>
        </dl>
        <p class="mt-2 text-sm text-muted" aria-live="polite">
            <span x-show="!failed">Counted as of <span x-text="refreshedAt">{{ $refreshedAt->inShowTz()->format('g:i A') }}</span>.</span>
            <span x-cloak x-show="failed" class="font-semibold text-bad">Could not refresh just now.</span>
            <button type="button" class="ml-1 font-semibold underline" x-on:click="refresh()">Refresh</button>
        </p>

        @if ($event->isVotingOpen())
            <div class="mt-6">
                @auth
                    @unless (auth()->user()->isAdmin())
                        <a href="{{ route('ballot.index', ['q' => $car->entry_number]) }}" class="btn btn-primary w-full">Vote for car #{{ $car->entry_number }}</a>
                    @endunless
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary w-full">Sign in to vote</a>
                    <p class="mt-2 text-sm text-muted">Voting is for registered contestants. Your login code is on your ballot.</p>
                @endauth
            </div>
        @endif
    </div>
</article>
@endsection
