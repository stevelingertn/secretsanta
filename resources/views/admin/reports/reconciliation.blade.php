@extends($print ? 'layouts.print' : 'layouts.admin')

@section('content')
    @include('admin.reports._header')

    <p class="mb-4 text-sm text-muted">Capacity comes from registered cars and any allowance overrides, not from the number of contestants.</p>

    <div class="overflow-x-auto">
        <table class="table-plain">
            <tbody>
                <tr><td>Registered cars</td><td>{{ $registered_cars }}</td></tr>
                <tr><td>Default capacity (cars &times; votes per car)</td><td>{{ $default_capacity }}</td></tr>
                <tr><td>Contestants</td><td>{{ $contestants }}</td></tr>
                <tr><td>Contestants with an allowance override</td><td>{{ $contestants_with_override }}</td></tr>
                <tr><td class="font-semibold">Effective capacity</td><td class="font-semibold">{{ $effective_capacity }}</td></tr>
                <tr><td>Online votes</td><td>{{ $online_votes }}</td></tr>
                <tr><td>Manual votes</td><td>{{ $manual_votes }}</td></tr>
                <tr><td class="font-semibold">Contestant votes cast</td><td class="font-semibold">{{ $contestant_votes }}</td></tr>
                <tr><td>Remaining capacity</td><td>{{ $remaining_capacity }}</td></tr>
                <tr>
                    <td>Contestant votes within effective capacity</td>
                    <td class="{{ $within_capacity ? 'text-ok' : 'text-bad' }} font-semibold">{{ $within_capacity ? 'Pass' : 'Fail' }}</td>
                </tr>
                <tr>
                    <td>Participants who used more than their allowance</td>
                    <td class="{{ $sanity_passed ? 'text-ok' : 'text-bad' }} font-semibold">{{ $over_capacity_participants }} ({{ $sanity_passed ? 'Pass' : 'Fail' }})</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-6 mb-2 text-lg">System tiebreak votes</h2>
    <p class="mb-2 text-sm text-muted">Tiebreak votes are award-scoped system votes, separate from contestant votes above.</p>
    <div class="overflow-x-auto">
        <table class="table-plain">
            <thead>
                <tr>
                    <th>Award scope</th>
                    <th>Car</th>
                    <th>System votes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tiebreak_votes as $tb)
                    <tr>
                        <td>{{ $tb['scope_label'] }}</td>
                        <td>#{{ $tb['car']->entry_number }} {{ $tb['car']->vehicle() }}</td>
                        <td>{{ $tb['votes'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">No ties have been resolved.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
