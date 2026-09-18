@extends('layouts.admin', ['title' => 'Cars'])

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-4xl uppercase">Cars</h1>
    @if ($hasContestants)
        <a href="{{ route('admin.cars.create') }}" class="btn btn-primary btn-sm">Register car</a>
    @else
        <a href="{{ route('admin.contestants.create') }}" class="btn btn-primary btn-sm">Register a contestant first</a>
    @endif
</div>

<form method="GET" class="mt-4 flex flex-wrap gap-3">
    <label for="q" class="sr-only">Search by number or vehicle</label>
    <input id="q" type="search" name="q" value="{{ $q }}" class="field flex-1" placeholder="Search by car number or vehicle">
    <label for="class" class="sr-only">Class</label>
    <select id="class" name="class" class="field w-auto">
        <option value="">All classes</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected($classId == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <button class="btn btn-ghost">Filter</button>
</form>

<div class="mt-4 overflow-x-auto">
    <table class="table-plain">
        <thead>
            <tr>
                <th>#</th>
                <th>Vehicle</th>
                <th>Class</th>
                <th>Owner</th>
                <th>Votes</th>
                <th>Photo</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($cars as $car)
                <tr>
                    <td><span class="plate plate-sm">{{ $car->entry_number }}</span></td>
                    <td>{{ $car->description }}</td>
                    <td>{{ $car->category->name }}</td>
                    <td>{{ $car->participant->user->name }}</td>
                    <td class="tabular-nums">{{ $car->votes_count }}</td>
                    <td>{{ $car->photo_path ? 'Yes' : 'No' }}</td>
                    <td><a href="{{ route('admin.cars.edit', $car) }}" class="btn btn-ghost btn-sm">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">No cars yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $cars->links() }}</div>
@endsection
