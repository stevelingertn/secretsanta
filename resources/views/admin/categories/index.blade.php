@extends('layouts.admin', ['title' => 'Classes'])

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-4xl uppercase">Classes</h1>
    @if ($canCreate)
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary btn-sm">Add class</a>
    @endif
</div>

<div class="mt-4 overflow-x-auto">
    <table class="table-plain">
        <thead>
            <tr><th>ID</th><th>Name</th><th>Cars in this event</th><th></th></tr>
        </thead>
        <tbody>
            @foreach ($categories as $category)
                <tr>
                    <td class="tabular-nums">{{ $category->id }}</td>
                    <td>{{ $category->name }}</td>
                    <td class="tabular-nums">{{ $category->cars_count }}</td>
                    <td class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-ghost btn-sm">Edit</a>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete class {{ $category->name }}?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-ghost btn-sm text-bad">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
