@extends($print ? 'layouts.print' : 'layouts.admin')

@section('content')
    @include('admin.reports._header')

    @unless ($print)
        <form method="GET" class="no-print mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label for="q" class="field-label">Search</label>
                <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Car number or vehicle" class="field">
            </div>
            <div>
                <label for="category" class="field-label">Class</label>
                <select id="category" name="category" class="field">
                    <option value="">All classes</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['category'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
            @if ($filters['q'] || $filters['category'])
                <a href="{{ route('admin.reports.show', 'votes-by-car') }}" class="btn btn-sm btn-ghost">Clear</a>
            @endif
        </form>
    @endunless

    <div class="overflow-x-auto">
        <table class="table-plain">
            <thead>
                <tr>
                    <th>Entry</th>
                    <th>Vehicle</th>
                    <th>Class</th>
                    <th>Online</th>
                    <th>Manual</th>
                    <th>Contestant votes</th>
                    <th>Tiebreaker (system)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><span class="plate plate-sm">{{ $row['entry_number'] }}</span></td>
                        <td>{{ $row['vehicle'] }}</td>
                        <td>{{ $row['class'] }}</td>
                        <td>{{ $row['online_votes'] }}</td>
                        <td>{{ $row['manual_votes'] }}</td>
                        <td class="font-semibold">{{ $row['contestant_votes'] }}</td>
                        <td>
                            @forelse ($row['tiebreak_votes'] as $tb)
                                <div>+{{ $tb['votes'] }} Tiebreaker (system), {{ $tb['scope_label'] }}</div>
                            @empty
                                <span class="text-muted">None</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No cars registered.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
