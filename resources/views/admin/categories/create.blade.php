@extends('layouts.admin', ['title' => 'Add class'])

@section('content')
<h1 class="text-4xl uppercase">Add class</h1>

<form method="POST" action="{{ route('admin.categories.store') }}" class="mt-6 grid max-w-md gap-4">
    @csrf
    <div>
        <label for="id" class="field-label">ID</label>
        <input id="id" name="id" type="number" min="1" value="{{ old('id', $nextId) }}" class="field" required>
        @error('id') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="name" class="field-label">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" class="field" required>
        @error('name') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <div class="flex gap-2">
        <button class="btn btn-primary">Add</button>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost">Cancel</a>
    </div>
</form>
@endsection
