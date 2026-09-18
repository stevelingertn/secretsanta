@extends('layouts.admin', ['title' => 'Edit class'])

@section('content')
<h1 class="text-4xl uppercase">Edit class</h1>

@if ($locked)
    <div class="card mt-4 border-2 border-warn p-4">
        <p class="font-semibold">This class has been used in a past or active show.</p>
        <p class="text-muted">Renaming it would rewrite history, so it is locked.</p>
    </div>
@else
    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="mt-6 grid max-w-md gap-4">
        @csrf
        @method('PUT')
        <div>
            <label for="name" class="field-label">Name</label>
            <input id="name" name="name" value="{{ old('name', $category->name) }}" class="field" required>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="flex gap-2">
            <button class="btn btn-primary">Save</button>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
@endif
@endsection
