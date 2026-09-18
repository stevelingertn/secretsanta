@extends($print ? 'layouts.print' : 'layouts.admin')

@section('content')
    @include('admin.reports._header')

    <div class="overflow-x-auto">
        <table class="table-plain">
            <thead>
                <tr>
                    <th>Award</th>
                    <th>Entry</th>
                    <th>Vehicle</th>
                    <th>Owner</th>
                    <th>Contestant votes</th>
                    <th>Tiebreaker (system)</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="font-semibold">{{ $row['title'] }}</td>
                        <td>{{ $row['entry_number'] ? '#'.$row['entry_number'] : '-' }}</td>
                        <td>{{ $row['vehicle'] ?? '-' }}</td>
                        <td>{{ $row['owner_name'] ?? '-' }}</td>
                        <td>{{ $row['contestant_votes'] }}</td>
                        <td>{{ $row['system_votes'] ? '+'.$row['system_votes'] : '-' }}</td>
                        <td>{{ $row['explanation'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @unless ($finalized)
        <p class="mt-4 text-sm text-muted">These results are provisional until an admin finalizes the event on the Results page.</p>
    @endunless
@endsection
