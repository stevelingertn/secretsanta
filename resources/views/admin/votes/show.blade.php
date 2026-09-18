@extends('layouts.admin')

@section('content')
<div class="max-w-xl space-y-4">
    <h1 class="text-2xl">Vote #{{ $vote->id }}</h1>
    <p class="text-sm font-semibold">Votes are final and cannot be edited or removed.</p>

    <table class="table-plain">
        <tbody>
            <tr><td>Car</td><td><span class="plate plate-sm">{{ $vote->car->entry_number }}</span> {{ $vote->car->vehicle() }}</td></tr>
            <tr><td>Class</td><td>{{ $vote->car->category?->name }}</td></tr>
            <tr><td>Source</td><td>{{ $vote->source->label() }}</td></tr>
            <tr><td>Entered by</td><td>{{ $vote->enteredBy?->name ?? '-' }}</td></tr>
            <tr><td>Ballot submission</td><td>#{{ $vote->ballot_submission_id }}</td></tr>
            <tr><td>Timestamp</td><td>{{ $vote->created_at->inShowTz()->format('M j, Y g:i A') }}</td></tr>
        </tbody>
    </table>

    <a href="{{ route('admin.votes.index') }}" class="btn btn-sm btn-ghost">Back to votes</a>
</div>
@endsection
