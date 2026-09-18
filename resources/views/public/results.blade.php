@extends('layouts.public', ['title' => 'Results'])

@section('content')
<h1 class="text-4xl uppercase sm:text-5xl">Results</h1>

@if ($awards->isEmpty())
    <div class="card mt-5 p-6">
        @if ($event->isVotingOpen())
            <p class="text-lg font-semibold">Voting is still open.</p>
            <p class="mt-1 text-muted">Winners are announced after voting closes and results are final. Live vote counts are on the <a class="underline" href="{{ route('gallery') }}">cars page</a>.</p>
        @elseif ($event->status === \App\Enums\EventStatus::VotingClosed)
            <p class="text-lg font-semibold">Voting has closed. Results are being checked.</p>
            <p class="mt-1 text-muted">Check back shortly for the final winners.</p>
        @else
            <p class="text-lg font-semibold">Voting has not opened yet.</p>
        @endif
    </div>
@else
    @php($overall = $awards->first())
    <section class="mt-5 overflow-hidden rounded-xl border-4 border-ink bg-white" aria-labelledby="best-overall">
        <div class="checker-thin"></div>
        <div class="p-5 sm:p-6">
            <h2 id="best-overall" class="text-2xl uppercase text-brand-red">Best Overall</h2>
            @if ($overall->car_id)
                <div class="mt-3 flex items-center gap-4">
                    <span class="plate plate-lg" aria-hidden="true">{{ $overall->entry_number }}</span>
                    <div>
                        <p class="font-display text-3xl font-bold leading-tight sm:text-4xl">{{ $overall->vehicle }}</p>
                        <p class="text-muted">Car #{{ $overall->entry_number }} &middot; {{ $overall->category_name }} &middot; {{ $overall->contestant_votes }} contestant {{ Str::plural('vote', $overall->contestant_votes) }}@if ($overall->system_votes) &middot; won a tiebreaker @endif</p>
                    </div>
                </div>
            @else
                <p class="mt-2 text-lg">{{ $overall->explanation }}</p>
            @endif
        </div>
    </section>

    <h2 class="mt-8 text-3xl uppercase">Class winners</h2>
    <ul class="mt-3 grid gap-3 sm:grid-cols-2" role="list">
        @foreach ($awards->skip(1) as $award)
            <li class="card flex items-center gap-4 p-4">
                @if ($award->car_id)
                    <span class="plate" aria-hidden="true">{{ $award->entry_number }}</span>
                    <div class="min-w-0">
                        <p class="font-display text-lg font-bold uppercase tracking-wide text-brand-blue">{{ $award->category_name }}</p>
                        <p class="font-semibold">{{ $award->vehicle }}</p>
                        <p class="text-sm text-muted">Car #{{ $award->entry_number }} &middot; {{ $award->contestant_votes }} {{ Str::plural('vote', $award->contestant_votes) }}@if ($award->system_votes) &middot; won a tiebreaker @endif</p>
                    </div>
                @else
                    <span class="plate text-muted" aria-hidden="true">&ndash;</span>
                    <div>
                        <p class="font-display text-lg font-bold uppercase tracking-wide text-brand-blue">{{ $award->category_name }}</p>
                        <p class="text-muted">{{ $award->explanation }}</p>
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
@endif
@endsection
