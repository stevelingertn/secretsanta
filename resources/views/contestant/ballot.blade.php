@extends('layouts.public', ['title' => 'My ballot'])

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-4xl uppercase sm:text-5xl">My ballot</h1>
        <p class="mt-1 text-muted">
            {{ $participant->user->name }}
            @if ($participant->voter_number) &middot; Voter #{{ $participant->voter_number }} @endif
        </p>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn btn-sm btn-ghost">Sign out</button>
    </form>
</div>

<dl class="mt-5 grid grid-cols-3 gap-3 text-center">
    <div class="card p-3">
        <dt class="text-sm font-semibold uppercase tracking-wide text-muted">Votes</dt>
        <dd class="font-display text-4xl font-extrabold tabular-nums">{{ $summary['allowance'] }}</dd>
    </div>
    <div class="card p-3">
        <dt class="text-sm font-semibold uppercase tracking-wide text-muted">Used</dt>
        <dd class="font-display text-4xl font-extrabold tabular-nums">{{ $summary['used'] }}</dd>
    </div>
    <div class="rounded-xl border-2 border-ink bg-brand-yellow p-3">
        <dt class="text-sm font-semibold uppercase tracking-wide">Left</dt>
        <dd class="font-display text-4xl font-extrabold tabular-nums">{{ $summary['remaining'] }}</dd>
    </div>
</dl>
<p class="mt-2 text-sm text-muted">
    You get 5 votes for each car you entered. Each car can get one vote from you, including your own. Paper and online votes share the same total.
</p>

@if ($errors->any())
    <div class="mt-4 rounded-lg border-2 border-bad bg-bad-soft px-4 py-3 text-bad" role="alert">
        <p class="font-bold">Nothing was saved.</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach (array_unique($errors->all()) as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($event->isVotingOpen() && $summary['remaining'] > 0)
    <form method="POST" action="{{ route('ballot.review') }}" class="mt-6"
          x-data="ballotPicker({{ $summary['remaining'] }}, @js($preselected))" x-init="query = @js($initialQuery)">
        @csrf
        <template x-for="n in selected" :key="n">
            <input type="hidden" name="cars[]" :value="n">
        </template>

        <h2 class="text-3xl uppercase">Choose cars</h2>
        <label for="ballot-search" class="sr-only">Find a car by number or vehicle</label>
        <input id="ballot-search" type="search" x-model="query" class="field mt-3" placeholder="Find by car number or vehicle" autocomplete="off">

        <ul class="mt-3 divide-y divide-line overflow-hidden rounded-xl border border-line bg-white" role="list">
            @foreach ($cars as $car)
                @php($voted = in_array($car->id, $votedCarIds, true))
                <li x-show="matches(@js(strtolower($car->entry_number.' '.$car->description.' '.$car->category->name)))">
                    @if ($voted)
                        <div class="flex items-center gap-3 px-3 py-3 opacity-70">
                            <span class="plate plate-sm">{{ $car->entry_number }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold">{{ $car->description }}</span>
                                <span class="block text-sm text-muted">{{ $car->category->name }}</span>
                            </span>
                            <span class="badge bg-ok-soft text-ok">Voted</span>
                        </div>
                    @else
                        <button type="button" class="flex w-full items-center gap-3 px-3 py-3 text-left transition-colors"
                                x-on:click="toggle({{ $car->entry_number }})"
                                x-bind:class="isSelected({{ $car->entry_number }}) ? 'bg-brand-yellow' : (full ? 'opacity-50' : 'hover:bg-paper')"
                                x-bind:aria-pressed="isSelected({{ $car->entry_number }}).toString()"
                                x-bind:disabled="full && !isSelected({{ $car->entry_number }})">
                            <span class="plate plate-sm">{{ $car->entry_number }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold">{{ $car->description }}</span>
                                <span class="block text-sm text-muted">{{ $car->category->name }}</span>
                            </span>
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border-2 border-ink bg-white" aria-hidden="true">
                                <svg x-show="isSelected({{ $car->entry_number }})" viewBox="0 0 20 20" class="h-5 w-5 fill-ink"><path d="M7.6 14.2 3.4 10l1.4-1.4 2.8 2.8 7.6-7.6L16.6 5z"/></svg>
                            </span>
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="sticky bottom-0 z-10 -mx-4 mt-4 border-t-2 border-ink bg-white px-4 py-3 shadow-[0_-4px_12px_rgba(0,0,0,0.08)]">
            <div class="mx-auto flex max-w-6xl items-center gap-3">
                <p class="flex-1 font-semibold" aria-live="polite">
                    <span x-text="selected.length">0</span> of {{ $summary['remaining'] }} selected
                    <span x-cloak x-show="full" class="block text-sm font-normal text-muted">That is all your remaining votes.</span>
                </p>
                <button class="btn btn-primary" x-bind:disabled="selected.length === 0">Review votes</button>
            </div>
        </div>
    </form>
@elseif ($event->isVotingOpen())
    <div class="card mt-6 p-5">
        <p class="text-lg font-semibold">You have used all of your votes. Thank you for voting.</p>
    </div>
@elseif ($event->status === \App\Enums\EventStatus::Setup)
    <div class="card mt-6 p-5">
        <p class="text-lg font-semibold">Voting has not opened yet.</p>
        <p class="text-muted">Come back to this page once voting opens. Your login code stays the same.</p>
    </div>
@else
    <div class="card mt-6 p-5">
        <p class="text-lg font-semibold">Voting is closed.</p>
        <a href="{{ route('results') }}" class="mt-2 inline-block font-semibold underline">See results</a>
    </div>
@endif

<section class="mt-8" aria-labelledby="my-votes">
    <h2 id="my-votes" class="text-3xl uppercase">My votes</h2>
    @if ($myVotes->isEmpty())
        <p class="mt-2 text-muted">No votes yet.</p>
    @else
        <ul class="mt-3 grid gap-2 sm:grid-cols-2" role="list">
            @foreach ($myVotes as $vote)
                <li class="card flex items-center gap-3 p-3">
                    <span class="plate plate-sm">{{ $vote->car->entry_number }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{{ $vote->car->description }}</span>
                        <span class="block text-sm text-muted">{{ $vote->car->category->name }} &middot; {{ $vote->source->label() }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
