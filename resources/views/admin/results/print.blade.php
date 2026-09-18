@extends('layouts.print', ['title' => 'Results', 'back' => route('admin.results.show')])

@section('content')
<div class="checker-thin"></div>
<header class="mt-3 flex items-start justify-between gap-4">
    <div>
        <h1 class="text-3xl uppercase">{{ $event->name }}</h1>
        <p>Award results &middot; {{ $event->year }}</p>
    </div>
    <div class="text-right text-sm">
        @if ($final)
            <p class="font-display text-2xl font-extrabold uppercase">Final</p>
            <p>Finalized {{ $event->finalized_at->inShowTz()->format('M j, Y g:i A') }}</p>
        @else
            <p class="font-display text-2xl font-extrabold uppercase text-brand-red">Provisional</p>
            <p>{{ $pending ? 'Ties pending. Not final.' : 'Not yet finalized.' }}</p>
        @endif
        <p>Printed {{ $generatedAt->inShowTz()->format('M j, Y g:i A') }}</p>
    </div>
</header>

<div class="mt-4 space-y-2">
    @foreach ($rows as $row)
        @include('admin.results._row', ['row' => $row, 'showActions' => false])
    @endforeach
</div>

<p class="mt-4 text-xs text-muted">Counts are contestant votes. A tiebreaker (system) vote applies only to the award it decided. A car can win one award. Classes with no eligible voted car have no winner.</p>
@endsection
