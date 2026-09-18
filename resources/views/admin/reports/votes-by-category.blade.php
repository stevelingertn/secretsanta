@extends($print ? 'layouts.print' : 'layouts.admin')

@section('content')
    @include('admin.reports._header')

    @forelse ($rows as $group)
        <div class="mb-6 avoid-break">
            <h2 class="mb-2 text-lg">{{ $group['category']->name }}: {{ $group['total_votes'] }} contestant {{ Str::plural('vote', $group['total_votes']) }}</h2>
            <div class="overflow-x-auto">
                <table class="table-plain">
                    <thead>
                        <tr>
                            <th>Entry</th>
                            <th>Vehicle</th>
                            <th>Contestant votes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($group['cars'] as $row)
                            <tr>
                                <td><span class="plate plate-sm">{{ $row['car']->entry_number }}</span></td>
                                <td>{{ $row['car']->vehicle() }}</td>
                                <td>{{ $row['votes'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">No cars in this class.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @php($outcome = $group['outcome'])
            @if ($outcome)
                <p class="mt-2 text-sm">
                    @if ($outcome->status === 'winner')
                        Winner: entry {{ $outcome->car->entry_number }}, {{ $outcome->car->vehicle() }}
                        ({{ $outcome->contestantVotes }} contestant {{ Str::plural('vote', $outcome->contestantVotes) }}@if ($outcome->systemVotes) , +{{ $outcome->systemVotes }} Tiebreaker (system) @endif).
                    @else
                        {{ $outcome->explanation }}
                    @endif
                </p>
            @endif
        </div>
    @empty
        <p>No classes have entries yet.</p>
    @endforelse
@endsection
