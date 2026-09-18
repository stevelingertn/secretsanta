@php
    $boxCount = max(1, min(60, $summary['allowance']));
@endphp
<div class="avoid-break rounded-xl border-2 border-ink p-5">
    <div class="checker-thin"></div>
    <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-muted">{{ $event->name }} @if($event->year) &middot; {{ $event->year }} @endif</p>
            <h2 class="text-3xl uppercase">{{ $participant->user->name }}</h2>
            @if ($participant->voter_number)
                <p class="text-muted">Voter #{{ $participant->voter_number }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-1">
            @foreach ($participant->cars as $car)
                <span class="plate plate-sm">{{ $car->entry_number }}</span>
            @endforeach
        </div>
    </div>

    <div class="mt-4 grid grid-cols-3 gap-3 text-center">
        <div class="rounded-lg border border-line p-2">
            <p class="text-xs uppercase text-muted">Total votes</p>
            <p class="text-2xl font-bold tabular-nums">{{ $summary['allowance'] }}</p>
        </div>
        <div class="rounded-lg border border-line p-2">
            <p class="text-xs uppercase text-muted">Already used</p>
            <p class="text-2xl font-bold tabular-nums">{{ $summary['used'] }}</p>
        </div>
        <div class="rounded-lg border border-line p-2">
            <p class="text-xs uppercase text-muted">Remaining</p>
            <p class="text-2xl font-bold tabular-nums">{{ $summary['remaining'] }}</p>
        </div>
    </div>

    <div class="mt-4 rounded-lg border-2 border-ink bg-paper p-3 text-center">
        <p class="text-xs uppercase tracking-wide text-muted">Login code</p>
        <p class="font-display text-4xl font-extrabold tracking-widest">{{ $code }}</p>
        <p class="mt-1 text-sm">Sign in at <span class="font-semibold">{{ route('login') }}</span></p>
    </div>

    <p class="mt-3 text-sm">
        Sign in at the address above with your login code to vote on your phone, or fill in this paper ballot
        and hand it to the registration table. Paper and online votes share the same total. Pick each car only
        once. Your own car counts.
    </p>
    <p class="mt-1 text-sm text-muted">
        Votes already cast online or on paper reduce what is left. You have {{ $summary['remaining'] }} left to use.
    </p>

    <div class="mt-3">
        <p class="field-label">Write the numbers of the cars you vote for:</p>
        <div class="grid grid-cols-6 gap-2 sm:grid-cols-10">
            @for ($i = 0; $i < $boxCount; $i++)
                <div class="flex h-10 items-center justify-center rounded border-2 border-ink/40 text-sm text-muted">&nbsp;</div>
            @endfor
        </div>
    </div>
</div>
