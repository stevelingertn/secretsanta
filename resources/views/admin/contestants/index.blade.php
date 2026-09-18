@extends('layouts.admin', ['title' => 'Contestants'])

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-4xl uppercase">Contestants</h1>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.contestants.ballots') }}" class="btn btn-ghost btn-sm">Print all ballots</a>
        <a href="{{ route('admin.contestants.create') }}" class="btn btn-primary btn-sm">Register contestant</a>
    </div>
</div>

<form method="GET" class="mt-4">
    <label for="q" class="sr-only">Search by name, voter number, or car number</label>
    <input id="q" type="search" name="q" value="{{ $q }}" class="field" placeholder="Search by name, voter number, or car number">
</form>

<div class="mt-4 overflow-x-auto">
    <table class="table-plain">
        <thead>
            <tr>
                <th>Voter #</th>
                <th>Name</th>
                <th>Cars</th>
                <th>Allowance</th>
                <th>Used</th>
                <th>Left</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($participants as $participant)
                @php
                    $cars = $participant->cars;
                    $allowance = $participant->allowance_override ?? ($cars->count() * $event->votes_per_car);
                    $used = $participant->votes_count;
                @endphp
                <tr>
                    <td>{{ $participant->voter_number ?? 'Not set' }}</td>
                    <td><a href="{{ route('admin.contestants.show', $participant) }}" class="font-semibold underline">{{ $participant->user->name }}</a></td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($cars as $car)
                                <span class="plate plate-sm">{{ $car->entry_number }}</span>
                            @empty
                                <span class="text-muted">None</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="tabular-nums">{{ $allowance }}</td>
                    <td class="tabular-nums">{{ $used }}</td>
                    <td class="tabular-nums">{{ max(0, $allowance - $used) }}</td>
                    <td><a href="{{ route('admin.contestants.show', $participant) }}" class="btn btn-ghost btn-sm">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">No contestants yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $participants->links() }}</div>
@endsection
