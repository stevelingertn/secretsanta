@extends('layouts.admin', ['title' => 'Event'])

@section('content')
<h1 class="text-4xl uppercase">Event</h1>

<div class="mt-5 grid gap-6 lg:grid-cols-[3fr_2fr]">
    <form method="POST" action="{{ route('admin.event.update') }}" class="card space-y-4 p-5">
        @csrf
        @method('PUT')
        <h2 class="text-2xl uppercase">{{ $event ? 'Show details' : 'Create the show' }}</h2>
        <div>
            <label for="name" class="field-label">Event name</label>
            <input id="name" name="name" class="field" required maxlength="120" value="{{ old('name', $event?->name ?? 'Secret Santa Car Show') }}">
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="year" class="field-label">Year</label>
                <input id="year" name="year" type="number" inputmode="numeric" class="field" required value="{{ old('year', $event?->year ?? now()->year) }}">
                @error('year')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="show_date" class="field-label">Show date</label>
                <input id="show_date" name="show_date" type="date" class="field" value="{{ old('show_date', $event?->show_date?->format('Y-m-d')) }}">
                @error('show_date')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label for="location" class="field-label">Location</label>
            <input id="location" name="location" class="field" maxlength="120" value="{{ old('location', $event?->location ?? 'Oakwood, Georgia') }}">
        </div>
        <div>
            <label for="details" class="field-label">Details <span class="font-normal text-muted">(optional, shown to admins only)</span></label>
            <textarea id="details" name="details" rows="3" class="field">{{ old('details', $event?->details) }}</textarea>
        </div>
        <div>
            <label for="votes_per_car" class="field-label">Votes per registered car</label>
            @if (! $event || $event->status === \App\Enums\EventStatus::Setup)
                <input id="votes_per_car" name="votes_per_car" type="number" min="1" max="50" class="field max-w-32" value="{{ old('votes_per_car', $event?->votes_per_car ?? 5) }}">
            @else
                <p class="field bg-paper py-3">{{ $event->votes_per_car }} <span class="text-sm text-muted">(locked once voting opens)</span></p>
            @endif
        </div>
        <button class="btn btn-secondary">Save details</button>
    </form>

    @if ($event)
        <div class="space-y-4">
            <section class="card p-5">
                <h2 class="text-2xl uppercase">Voting</h2>
                <p class="mt-1"><span class="badge border border-ink">{{ $event->status->label() }}</span></p>
                <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-muted">
                    <li>Register contestants and cars, then print ballots.</li>
                    <li>Open voting. New contestants and cars can still be added.</li>
                    <li>Enter returned paper ballots.</li>
                    <li>Click Voting Finished. Online and paper voting both stop.</li>
                    <li>Resolve any ties, then finalize and print results.</li>
                </ol>

                @if ($event->status === \App\Enums\EventStatus::Setup)
                    <form method="POST" action="{{ route('admin.event.open') }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="confirm" value="1" class="mt-1 h-6 w-6">
                            <span>Open voting now. Car numbers, owners and classes lock for existing cars.</span>
                        </label>
                        <button class="btn btn-primary w-full">Open voting</button>
                    </form>
                @elseif ($event->isVotingOpen())
                    <form method="POST" action="{{ route('admin.event.close') }}" class="mt-4 space-y-3"
                          x-data="{ sure: false }">
                        @csrf
                        <div class="rounded-lg border-2 border-warn bg-warn-soft p-3 text-warn">
                            <p class="font-bold">Enter every returned paper ballot first.</p>
                            <p class="text-sm">Voting Finished stops online and paper voting, freezes registration and allowances, and calculates results. It cannot be undone.</p>
                        </div>
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="confirm_paper" value="1" class="mt-1 h-6 w-6" x-model="sure">
                            <span>All returned paper ballots have been entered.</span>
                        </label>
                        <button class="btn btn-primary w-full" x-bind:disabled="!sure">Voting Finished</button>
                    </form>
                @else
                    <a href="{{ route('admin.results.show') }}" class="btn btn-primary mt-4 w-full">Go to results</a>
                @endif
            </section>
        </div>
    @endif
</div>
@endsection
