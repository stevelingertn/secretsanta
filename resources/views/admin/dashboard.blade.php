@extends('layouts.admin', ['title' => 'Dashboard'])

@section('content')
@if (! $stats)
    <div class="card p-6">
        <h1 class="text-3xl uppercase">No active event</h1>
        <p class="mt-2">Set up the show on the <a class="underline" href="{{ route('admin.event.edit') }}">Event page</a>.</p>
    </div>
@else
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-4xl uppercase">{{ $event->name }}</h1>
            <p class="text-muted">{{ $event->status->label() }}@if ($event->is_test) &middot; Test event @endif</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($event->allowsRegistration())
                <a href="{{ route('admin.contestants.create') }}" class="btn btn-secondary">Register contestant</a>
            @endif
            @if ($event->isVotingOpen())
                <a href="{{ route('admin.paper.index') }}" class="btn btn-primary">Enter paper ballot</a>
            @endif
            @if ($event->isClosedOrFinal())
                <a href="{{ route('admin.results.show') }}" class="btn btn-primary">Results</a>
            @endif
        </div>
    </div>

    @if ($stats['resultsPending'])
        <div class="mt-4 rounded-lg border-2 border-warn bg-warn-soft px-4 py-3 font-semibold text-warn">
            Results are provisional. {{ $stats['pendingTies'] }} {{ Str::plural('tie', $stats['pendingTies']) }} ready to resolve on the <a class="underline" href="{{ route('admin.results.show') }}">Results page</a>.
        </div>
    @endif

    <dl class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ([
            ['Cars entered', $stats['cars']],
            ['Contestants', $stats['contestants']],
            ['Votes cast', $stats['cast']],
            ['Votes remaining', $stats['remaining']],
            ['Online votes', $stats['online']],
            ['Manually entered', $stats['manual']],
            ['Total allowance', $stats['effective']],
            ['Default (5 per car)', $stats['defaultCapacity']],
        ] as [$label, $value])
            <div class="card p-4">
                <dt class="text-sm font-semibold uppercase tracking-wide text-muted">{{ $label }}</dt>
                <dd class="font-display text-4xl font-extrabold tabular-nums">{{ number_format($value) }}</dd>
            </div>
        @endforeach
    </dl>
    @if ($stats['overrides'])
        <p class="mt-2 text-sm text-muted">{{ $stats['overrides'] }} {{ Str::plural('contestant', $stats['overrides']) }} have an allowance override, so the total allowance differs from 5 per car.</p>
    @endif

    <section class="mt-8">
        <h2 class="text-2xl uppercase">Entries by class</h2>
        @if ($stats['byClass']->isEmpty())
            <p class="mt-2 text-muted">No cars registered yet.</p>
        @else
            <ul class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3" role="list">
                @foreach ($stats['byClass'] as $row)
                    <li class="flex items-center justify-between rounded-lg border border-line bg-white px-4 py-2">
                        <span class="font-semibold">{{ $row->name }}</span>
                        <span class="font-display text-2xl font-bold tabular-nums">{{ $row->total }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
@endsection
