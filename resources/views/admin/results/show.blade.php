@extends('layouts.admin', ['title' => 'Results'])

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-4xl uppercase">Results</h1>
        <p class="text-muted">{{ $event->name }}</p>
    </div>
    @if ($event->isClosedOrFinal())
        <a href="{{ route('admin.results.print') }}" class="btn btn-ghost">Print results</a>
    @endif
</div>

@if (! $event->isClosedOrFinal())
    <div class="card mt-5 p-5">
        <p class="text-lg font-semibold">Results are calculated after Voting Finished.</p>
        <p class="text-muted">Live counts are on the <a class="underline" href="{{ route('admin.reports.show', 'votes-by-car') }}">votes by car report</a>. Close voting from the <a class="underline" href="{{ route('admin.event.edit') }}">Event page</a>.</p>
    </div>
@else
    @if ($final)
        <div class="mt-4 rounded-lg border-2 border-ok bg-ok-soft px-4 py-3 font-semibold text-ok">Final. Finalized {{ $event->finalized_at->inShowTz()->format('M j, Y g:i A') }}. These results are stored and will not change.</div>
    @elseif ($pending)
        <div class="mt-4 rounded-lg border-2 border-warn bg-warn-soft px-4 py-3 text-warn">
            <p class="font-bold">Provisional: ties pending.</p>
            <p class="text-sm">Resolve Best Overall first if it is tied. Classes marked "waiting" depend on it. Each tie button picks one of the tied cars at random, once.</p>
        </div>
    @else
        <div class="mt-4 rounded-lg border-2 border-ink bg-brand-yellow px-4 py-3">
            <p class="font-bold">No ties left. Review the results, then finalize.</p>
        </div>
    @endif

    <div class="mt-5 space-y-3">
        @foreach ($rows as $row)
            @include('admin.results._row', ['row' => $row, 'showActions' => true])
        @endforeach
    </div>

    @if (! $final && ! $pending)
        <form method="POST" action="{{ route('admin.results.finalize') }}" class="card mt-6 space-y-3 p-5">
            @csrf
            <h2 class="text-2xl uppercase">Finalize</h2>
            <p>Finalizing stores these winners permanently. Printing and the public results page will show exactly this list.</p>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="confirm" value="1" class="mt-1 h-6 w-6">
                <span>These results are correct and final.</span>
            </label>
            <button class="btn btn-primary">Finalize results</button>
        </form>
    @endif
@endif
@endsection
