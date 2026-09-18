@extends('layouts.admin', ['title' => 'Paper ballots'])

@section('content')
<h1 class="text-4xl uppercase">Paper ballots</h1>

@if (! $event->isVotingOpen())
    <div class="mt-4 rounded-lg border-2 border-warn bg-warn-soft px-4 py-3 font-semibold text-warn">
        Voting is {{ $event->status === \App\Enums\EventStatus::Setup ? 'not open yet' : 'closed' }}. Paper ballots can be entered only while voting is open.
    </div>
@endif

<form method="POST" action="{{ route('admin.paper.find') }}" class="card mt-5 p-5" role="search">
    @csrf
    <label for="q" class="field-label">Voter number, car number, name, or login code</label>
    <div class="flex gap-2">
        <input id="q" name="q" value="{{ ctype_digit(ltrim($q, '#')) ? $q : '' }}" class="field font-display text-2xl" autofocus autocomplete="off" placeholder="e.g. 42">
        <button class="btn btn-secondary">Find</button>
    </div>
    <p class="mt-2 text-sm text-muted">Press Enter. A single match opens the entry form. This page never creates contestants; register new people on the Contestants page.</p>
</form>

@if ($q !== '')
    <section class="mt-5">
        @if ($matches->isEmpty())
            <p class="font-semibold">No contestant matches that search.</p>
        @else
            <h2 class="text-2xl uppercase">{{ $matches->count() }} matches</h2>
            <ul class="mt-2 divide-y divide-line overflow-hidden rounded-xl border border-line bg-white" role="list">
                @foreach ($matches as $match)
                    <li>
                        <a href="{{ route('admin.paper.create', $match) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-paper">
                            <span class="font-display text-xl font-bold">Voter #{{ $match->voter_number ?? '-' }}</span>
                            <span class="flex-1 font-semibold">{{ $match->user->name }}</span>
                            <span class="flex flex-wrap gap-1">
                                @foreach ($match->cars as $car)
                                    <span class="plate plate-sm">{{ $car->entry_number }}</span>
                                @endforeach
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
@endsection
