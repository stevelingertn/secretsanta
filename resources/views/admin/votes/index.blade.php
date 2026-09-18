@extends('layouts.admin')

@section('content')
<div class="space-y-4">
    <h1 class="text-2xl">Votes</h1>
    <p class="text-muted">{{ $event->name }} {{ $event->year }}. Online: {{ $counts['online'] }}. Manual: {{ $counts['manual'] }}. Total: {{ $counts['online'] + $counts['manual'] }}.</p>
    <p class="text-sm font-semibold">Votes are final and cannot be edited or removed.</p>

    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div>
            <label for="source" class="field-label">Source</label>
            <select id="source" name="source" class="field">
                <option value="">All sources</option>
                <option value="online" @selected($filters['source'] === 'online')>Online</option>
                <option value="manual" @selected($filters['source'] === 'manual')>Manually entered</option>
            </select>
        </div>
        <div>
            <label for="car" class="field-label">Car number</label>
            <input type="text" id="car" name="car" value="{{ $filters['car'] }}" class="field">
        </div>
        <div>
            <label for="category" class="field-label">Class</label>
            <select id="category" name="category" class="field">
                <option value="">All classes</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category'] === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
        @if ($filters['source'] || $filters['car'] || $filters['category'])
            <a href="{{ route('admin.votes.index') }}" class="btn btn-sm btn-ghost">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto">
        <table class="table-plain">
            <thead>
                <tr>
                    <th>Car</th>
                    <th>Class</th>
                    <th>Source</th>
                    <th>Entered by</th>
                    <th>Ballot</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($votes as $vote)
                    <tr>
                        <td>
                            <a href="{{ route('admin.votes.show', $vote) }}" class="underline">
                                <span class="plate plate-sm">{{ $vote->car->entry_number }}</span> {{ $vote->car->vehicle() }}
                            </a>
                        </td>
                        <td>{{ $vote->car->category?->name }}</td>
                        <td>{{ $vote->source->label() }}</td>
                        <td>{{ $vote->enteredBy?->name ?? '-' }}</td>
                        <td>#{{ $vote->ballot_submission_id }}</td>
                        <td>{{ $vote->created_at->inShowTz()->format('M j, Y g:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No votes recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $votes->links() }}
</div>
@endsection
