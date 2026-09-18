{{-- One award line. Used by the results screen and the printout. --}}
@php($big = $row['scope'] === 'overall')
<div @class(['avoid-break rounded-xl border bg-white p-4', 'border-4 border-ink' => $big, 'border-line' => ! $big, 'border-2 border-warn bg-warn-soft' => in_array($row['status'], ['tie_pending', 'waiting'], true)])>
    <div class="flex flex-wrap items-start gap-4">
        @if ($row['entry_number'])
            <span @class(['plate', 'plate-lg' => $big])>{{ $row['entry_number'] }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <h3 @class(['uppercase', 'text-3xl text-brand-red' => $big, 'text-xl text-brand-blue' => ! $big])>{{ $row['title'] }}</h3>
            @if ($row['status'] === 'winner')
                <p class="text-lg font-semibold">{{ $row['vehicle'] }}</p>
                <p class="text-sm text-muted">
                    Car #{{ $row['entry_number'] }}@if ($big && $row['class']) &middot; {{ $row['class'] }}@endif
                    &middot; Owner: {{ $row['owner'] }}
                    &middot; {{ $row['votes'] }} contestant {{ Str::plural('vote', $row['votes']) }}
                    @if ($row['system'])
                        &middot; <span class="font-semibold text-ink">+{{ $row['system'] }} tiebreaker (system) vote for this award only</span>
                    @endif
                </p>
            @elseif ($row['status'] === 'tie_pending')
                <p class="font-semibold">Tie: {{ $row['explanation'] }}</p>
            @else
                <p class="font-semibold">{{ $row['explanation'] }}</p>
            @endif

            @if (! empty($row['candidates']) && ($row['status'] === 'tie_pending' || $row['system']))
                <p class="mt-1 text-sm">
                    Tied cars:
                    @foreach ($row['candidates'] as $c)
                        <span class="whitespace-nowrap">#{{ $c['entry_number'] }} ({{ $c['votes'] }})@if (! $loop->last), @endif</span>
                    @endforeach
                </p>
            @endif
        </div>

        @if (($showActions ?? false) && $row['can_resolve'])
            <form method="POST" action="{{ route('admin.results.tiebreak') }}" class="no-print w-full sm:w-auto" x-data="{ sure: false }">
                @csrf
                <input type="hidden" name="scope" value="{{ $row['scope'] }}">
                <button type="button" class="btn btn-primary w-full" x-show="!sure" x-on:click="sure = true">Break this tie</button>
                <div x-cloak x-show="sure" class="space-y-2">
                    <p class="text-sm">Picks one tied car at random and adds one system vote to this award only. This cannot be redone.</p>
                    <div class="flex gap-2">
                        <button class="btn btn-primary">Pick winner now</button>
                        <button type="button" class="btn btn-ghost" x-on:click="sure = false">Cancel</button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</div>
